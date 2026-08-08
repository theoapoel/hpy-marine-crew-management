<?php

namespace App\Console\Commands;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Import the crew dossiers extracted from the scanned document folders into ERP HPY.
 *
 * Source of truth is one JSON file per crew member under storage/app/crew-import,
 * written by hand from the PDFs (biodata, passport, seaman's book, MCU, COC/COE, COP,
 * vaccination book). Each file carries a `crew` block that maps onto Employee fields
 * and a `certificates` array that maps onto the "Employee Certificate" child table.
 *
 * The command is a dry run unless --write is given: nothing reaches ERP HPY until the
 * printed plan has been read. Matching is by seafarer code (stored in
 * Employee.employee_number), so re-running updates the same people instead of creating
 * duplicates.
 */
class ImportCrewFromFiles extends Command
{
    protected $signature = 'erp:import-crew
        {--write : actually create/update in ERP HPY (otherwise this is a dry run)}
        {--company= : company to file the crew under, defaults to services.erpnext.company}
        {--only= : comma separated file name fragments, e.g. "diar,tejo"}
        {--path= : directory holding the crew JSON files}';

    protected $description = 'Import crew dossiers from storage/app/crew-import into ERP HPY';

    /** Employee fields copied straight across when the JSON carries them. */
    private const PLAIN_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
        'designation', 'cell_number', 'personal_email', 'current_address',
        'permanent_address', 'person_to_be_contacted', 'emergency_phone_number',
        'relation', 'marital_status', 'blood_group', 'health_details',
        'passport_number', 'date_of_issue', 'valid_upto', 'place_of_issue',
        'bank_name', 'bank_ac_no', 'contract_end_date',
    ];

    /** app key => ERP HPY custom field. */
    private const CUSTOM_FIELDS = [
        'crew_status' => 'custom_crew_status',
        'nationality' => 'custom_nationality',
        'rank' => 'custom_rank',
        'seaman_book_no' => 'custom_seaman_book_no',
        'seaman_book_expiry' => 'custom_seaman_book_expiry',
        'vessel' => 'custom_vessel',
        'sign_on_date' => 'custom_sign_on_date',
    ];

    private const CREW_DOCTYPE = 'Employee';

    /**
     * The dossiers spell ranks the way the certificates do ("2nd Officer"); the Rank
     * doctype spells them out. Anything not listed here is passed through unchanged.
     */
    private const RANK_ALIASES = [
        '2nd officer' => 'Second Officer',
        '3rd officer' => 'Third Officer',
        '1st officer' => 'Chief Officer',
        'chief mate' => 'Chief Officer',
        '2nd engineer' => 'Second Engineer',
        '3rd engineer' => 'Third Engineer',
        '4th engineer' => 'Fourth Engineer',
        'electro technical officer' => 'Electrician',
        'engine cadet' => 'Engine Cadet',
        'deck cadet' => 'Deck Cadet',
        'cook' => 'Chief Cook',
        'fitter' => 'Oiler',
    ];

    /**
     * The Bernice is not in the fleet yet. Particulars come from the mustering pages of
     * the crew's own seaman's books; the flag is left blank because the books disagree
     * (Dadan's records Bahamas, Ronni's Marshall Islands).
     */
    private const BERNICE = [
        'vessel_name' => 'MT Bernice',
        'vessel_type' => 'Tanker',
        'sub_type' => 'Oil/Chemical Tanker',
        'gross_tonnage' => 25063,
        'imo_number' => '9220926',
        'flag_state' => 'Bahamas',
    ];

    private bool $write = false;

    public function handle(ErpnextClient $client): int
    {
        $this->write = (bool) $this->option('write');
        $directory = $this->option('path') ?: storage_path('app/crew-import');
        $company = $this->option('company') ?: config('services.erpnext.company');

        if (blank($company)) {
            $this->error('No company: pass --company, or set ERPNEXT_COMPANY.');

            return self::FAILURE;
        }

        $files = $this->files($directory);

        if ($files === []) {
            $this->error("No crew JSON files found in {$directory}.");

            return self::FAILURE;
        }

        $this->line($this->write
            ? "<comment>WRITING</comment> to ERP HPY, company <info>{$company}</info>"
            : "<comment>DRY RUN</comment> — nothing will be written. Company <info>{$company}</info>. Add --write to apply.");
        $this->newLine();

        $vessel = $this->ensureVessel($client, $company);
        $this->ensureDesignations($client, $files);

        $created = $updated = $failed = 0;

        foreach ($files as $file) {
            $dossier = json_decode((string) file_get_contents($file), true);

            if (! is_array($dossier) || ! isset($dossier['crew'])) {
                $this->error('  ! ' . basename($file) . ' is not a valid crew dossier, skipped.');
                $failed++;

                continue;
            }

            try {
                $outcome = $this->importOne($client, $dossier, $company, $vessel);
                $outcome === 'created' ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $this->error('  ! ' . basename($file) . ': ' . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Created: <info>{$created}</info>   Updated: <info>{$updated}</info>   Failed: <comment>{$failed}</comment>");

        if (! $this->write) {
            $this->newLine();
            $this->line('Dry run only. Re-run with <info>--write</info> to apply.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function files(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];

        if ($only = trim((string) $this->option('only'))) {
            $needles = array_filter(array_map('trim', explode(',', mb_strtolower($only))));

            $files = array_filter($files, function (string $file) use ($needles) {
                foreach ($needles as $needle) {
                    if (str_contains(mb_strtolower(basename($file)), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        sort($files);

        return array_values($files);
    }

    /**
     * Employee.designation is a Link into the HR Designation doctype, which starts out
     * empty on a fresh instance — ERP HPY refuses the whole Employee otherwise. Create
     * the job titles the dossiers use before importing anyone.
     *
     * @param  array<int, string>  $files
     */
    private function ensureDesignations(ErpnextClient $client, array $files): void
    {
        $wanted = [];

        foreach ($files as $file) {
            $crew = json_decode((string) file_get_contents($file), true)['crew'] ?? [];

            foreach (['designation', 'rank'] as $key) {
                if (filled($crew[$key] ?? null)) {
                    $title = self::RANK_ALIASES[mb_strtolower((string) $crew[$key])] ?? $crew[$key];
                    $wanted[$title] = true;
                }
            }
        }

        $existing = array_column($client->list('Designation', ['name'], [], 500), 'name');
        $missing = array_diff(array_keys($wanted), $existing);

        if ($missing === []) {
            return;
        }

        $this->line('  designations to create: <info>' . implode(', ', $missing) . '</info>');
        $this->newLine();

        if (! $this->write) {
            return;
        }

        foreach ($missing as $title) {
            $client->create('Designation', ['designation_name' => $title]);
        }
    }

    /**
     * The crew all sail on the Bernice, which is not in the Vessel doctype yet. Returns
     * its ERP HPY name so custom_vessel can link to it, or null when it could not be
     * resolved (dry run, or creation refused).
     */
    private function ensureVessel(ErpnextClient $client, string $company): ?string
    {
        $rows = $client->list('Vessel', ['name'], [['vessel_name', '=', self::BERNICE['vessel_name']]], 1);

        if ($rows !== []) {
            $this->line("  vessel <info>{$rows[0]['name']}</info> already in the fleet");
            $this->newLine();

            if ($this->write) {
                // Particulars firmed up as more contracts were read; keep the record current.
                $client->update('Vessel', (string) $rows[0]['name'], self::BERNICE);
            }

            return (string) $rows[0]['name'];
        }

        $this->line('  <info>+</info> vessel ' . self::BERNICE['vessel_name'] . ' — not in the fleet yet, will be created');
        $this->newLine();

        if (! $this->write) {
            return null;
        }

        $created = $client->create('Vessel', self::BERNICE + ['company' => $company]);

        return (string) $created['name'];
    }

    /**
     * @param  array<string, mixed>  $dossier
     * @return 'created'|'updated'
     */
    private function importOne(ErpnextClient $client, array $dossier, string $company, ?string $vessel): string
    {
        $crew = $dossier['crew'];
        $label = (string) ($crew['name'] ?? 'unnamed');
        $existing = $this->findExisting($client, $crew, $company);

        $payload = $this->toErp($crew, $company, creating: $existing === null, vessel: $vessel);
        $certificates = $this->certificateRows($dossier['certificates'] ?? []);

        if ($existing === null) {
            $this->line("  <info>+</info> {$label} — new Employee, " . count($certificates) . ' certificates');

            if (! $this->write) {
                return 'created';
            }

            $row = $client->create(self::CREW_DOCTYPE, $payload);
            $client->update(self::CREW_DOCTYPE, (string) $row['name'], ['custom_certificates' => $certificates]);
            $this->line("      ERP HPY name: <info>{$row['name']}</info>");
            $this->assign($dossier, (string) $row['name'], $company, $vessel);

            return 'created';
        }

        $this->line("  <comment>~</comment> {$label} — updating {$existing}, " . count($certificates) . ' certificates');

        if ($this->write) {
            $client->update(self::CREW_DOCTYPE, $existing, $payload + ['custom_certificates' => $certificates]);
        }

        $this->assign($dossier, $existing, $company, $vessel);

        return 'updated';
    }

    /**
     * Setting Employee.custom_vessel is not enough: the vessel page lists who is aboard
     * from our own crew_assignments table, so each imported crew needs a berth there too.
     * Idempotent — an assignment already open on this vessel is left alone.
     *
     * @param  array<string, mixed>  $dossier
     */
    private function assign(array $dossier, string $employeeId, string $company, ?string $vessel): void
    {
        $crew = $dossier['crew'];

        // A crew with no sign-on date still belongs on the vessel's crew list — some SEAs
        // only take effect on the day of departure and never print the date.
        if ($vessel === null || blank($crew['vessel'] ?? null)) {
            return;
        }

        $already = CrewAssignment::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('vessel', $vessel)
            ->whereIn('status', ['planned', 'onboard'])
            ->exists();

        if ($already) {
            $this->line('      assignment already open');

            return;
        }

        $signOn = $crew['sign_on_date'] ?? null;
        $months = (int) ($crew['contract_months'] ?? 0);

        $this->line('      <info>+</info> assignment onboard ' . $vessel
            . ($signOn ? ' from ' . $signOn : ' (sign-on date not in the documents)'));

        if (! $this->write) {
            return;
        }

        // The code is dated by the sign-on where there is one, else by the SEA signature.
        $dated = $signOn ?? ($crew['sea_signed_date'] ?? null);

        $assignment = new CrewAssignment();
        $assignment->forceFill([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'assignment_code' => CrewAssignment::nextCode($dated ? Carbon::parse($dated) : null),
            'employee_id' => $employeeId,
            'crew_name' => $crew['name'] ?? '',
            'vessel' => $vessel,
            'rank' => $this->rank($crew),
            'company' => $company,
            'status' => 'onboard',
            'sign_on_date' => $signOn,
            'sign_on_port' => $this->signOnPort($dossier, $vessel),
            'contract_months' => $months ?: null,
            'planned_sign_off_date' => $crew['contract_end_date']
                ?? ($signOn && $months ? Carbon::parse($signOn)->addMonths($months)->toDateString() : null),
            'notes' => $signOn
                ? 'Imported from the crew document folder.'
                : 'Imported from the crew document folder. Sign-on date unknown: the SEA takes effect on the day of departure and prints no date.',
            'created_by' => 'erp:import-crew',
        ])->save();
    }

    /** @param array<string, mixed> $crew */
    private function rank(array $crew): ?string
    {
        if (blank($crew['rank'] ?? null)) {
            return null;
        }

        return self::RANK_ALIASES[mb_strtolower((string) $crew['rank'])] ?? $crew['rank'];
    }

    /**
     * The mustering pages record where each crew boarded; reuse it when the dossier's
     * sea service names this vessel.
     *
     * @param  array<string, mixed>  $dossier
     */
    private function signOnPort(array $dossier, string $vessel): ?string
    {
        foreach ($dossier['sea_service'] ?? [] as $leg) {
            if (str_contains(mb_strtolower((string) ($leg['vessel'] ?? '')), 'bernice')) {
                return $leg['sign_on_place'] ?? null;
            }
        }

        return null;
    }

    /**
     * Seafarer code is the stable identifier here; it is written to
     * Employee.employee_number so a re-run finds the same person. Falls back to an
     * exact employee_name match for crew whose code was never captured.
     *
     * @param  array<string, mixed>  $crew
     */
    private function findExisting(ErpnextClient $client, array $crew, string $company): ?string
    {
        $filters = [['company', '=', $company]];

        if ($code = $crew['seafarer_code'] ?? null) {
            $rows = $client->list(self::CREW_DOCTYPE, ['name'], [...$filters, ['employee_number', '=', (string) $code]], 1);

            if ($rows !== []) {
                return (string) $rows[0]['name'];
            }
        }

        $rows = $client->list(self::CREW_DOCTYPE, ['name'], [...$filters, ['employee_name', '=', (string) ($crew['name'] ?? '')]], 1);

        return $rows === [] ? null : (string) $rows[0]['name'];
    }

    /**
     * @param  array<string, mixed>  $crew
     * @return array<string, mixed>
     */
    private function toErp(array $crew, string $company, bool $creating, ?string $vessel): array
    {
        $payload = [];

        foreach (self::PLAIN_FIELDS as $field) {
            if (filled($crew[$field] ?? null)) {
                $payload[$field] = $crew[$field];
            }
        }

        foreach (self::CUSTOM_FIELDS as $app => $erp) {
            if (filled($crew[$app] ?? null)) {
                $payload[$erp] = $crew[$app];
            }
        }

        // ERP HPY only accepts blood groups carrying a rhesus sign. The books print the
        // group alone, so the sign is taken from the lab result quoted in health_details;
        // without one the field is left empty rather than guessed.
        if (filled($payload['blood_group'] ?? null)) {
            $group = strtoupper(trim((string) $payload['blood_group']));

            if (! str_ends_with($group, '+') && ! str_ends_with($group, '-')) {
                $health = mb_strtolower((string) ($crew['health_details'] ?? ''));

                $group = match (true) {
                    str_contains($health, 'rhesus positive') => $group . '+',
                    str_contains($health, 'rhesus negative') => $group . '-',
                    default => null,
                };
            }

            $group === null ? $payload['blood_group'] = '' : $payload['blood_group'] = $group;
        }

        if (filled($payload['custom_rank'] ?? null)) {
            $payload['custom_rank'] = self::RANK_ALIASES[mb_strtolower((string) $payload['custom_rank'])]
                ?? $payload['custom_rank'];
        }

        // custom_vessel is a Link: it must hold the Vessel's ERP HPY name, not "Bernice".
        if ($vessel === null) {
            unset($payload['custom_vessel']);
        } else {
            $payload['custom_vessel'] = $vessel;
        }

        if (filled($crew['seafarer_code'] ?? null)) {
            $payload['employee_number'] = (string) $crew['seafarer_code'];
        }

        // Employee.status only records whether the person is still with the company;
        // Onboard / Standby / Sign Off rides in custom_crew_status.
        $active = in_array($crew['crew_status'] ?? null, ['Onboard', 'Standby'], true);
        $payload['status'] = $active ? 'Active' : 'Left';

        if (! $active) {
            $payload['relieving_date'] = $crew['contract_end_date'] ?? now()->toDateString();
        }

        if ($creating) {
            $payload['company'] = $company;
            $payload['date_of_joining'] = $crew['date_of_joining']
                ?? $crew['sign_on_date']
                ?? now()->toDateString();
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $certificates
     * @return array<int, array<string, mixed>>
     */
    private function certificateRows(array $certificates): array
    {
        $rows = [];

        foreach ($certificates as $certificate) {
            $type = trim((string) ($certificate['certificate_type'] ?? ''));

            if ($type === '') {
                continue;
            }

            $rows[] = array_filter([
                'certificate_type' => $type,
                'certificate_number' => $certificate['certificate_number'] ?? null,
                'issued_by' => $certificate['issued_by'] ?? null,
                'issue_date' => $certificate['issue_date'] ?? null,
                'expiry_date' => $certificate['expiry_date'] ?? null,
                'status' => $certificate['status'] ?? $this->status($certificate['expiry_date'] ?? null),
                'remarks' => $certificate['remarks'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $rows;
    }

    private function status(?string $expiry): string
    {
        if (! $expiry) {
            return 'Valid';
        }

        $date = Carbon::parse($expiry);

        return match (true) {
            $date->isPast() => 'Expired',
            $date->lessThanOrEqualTo(now()->addDays(60)) => 'Expiring Soon',
            default => 'Valid',
        };
    }
}
