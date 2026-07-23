<?php

namespace App\Console\Commands;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sample crew documents, so the Documents menu (Certificates / Passport / Seaman Book
 * / Medical / Expiring) has real rows to show for each company.
 *
 * Creates a couple of sample Employees per company when a company has none, then fills
 * their certificate table with a spread of valid, expiring and expired papers.
 * Idempotent: an employee that already carries certificates is left alone.
 */
class SeedCrewDocuments extends Command
{
    protected $signature = 'erp:seed-crew-documents {--force : rewrite certificates even if some already exist}';

    protected $description = 'Give crew a sample set of certificates in ERP HPY';

    /** Sample crew to create when a company has no employees yet. */
    private const SAMPLE_CREW = [
        'Keenindo Bintas Marine' => [
            ['first_name' => 'Agus', 'last_name' => 'Salim', 'rank' => 'Chief Officer', 'gender' => 'Male', 'dob' => '1987-06-11'],
            ['first_name' => 'Rizal', 'last_name' => 'Fauzi', 'rank' => 'Second Engineer', 'gender' => 'Male', 'dob' => '1991-11-23'],
        ],
        'Donmar Jaladri Utama' => [
            ['first_name' => 'Made', 'last_name' => 'Suardana', 'rank' => 'Master', 'gender' => 'Male', 'dob' => '1983-02-17'],
            ['first_name' => 'Fitri', 'last_name' => 'Handayani', 'rank' => 'Messman', 'gender' => 'Female', 'dob' => '1995-09-05'],
        ],
    ];

    public function handle(ErpnextClient $client): int
    {
        $companies = $client->companies();

        foreach ($companies as $company) {
            $this->line("<info>{$company}</info>");

            $employees = $this->employeesOf($client, $company);

            if (count($employees) < 2) {
                $employees = array_merge($employees, $this->createSampleCrew($client, $company, 2 - count($employees)));
            }

            foreach (array_values($employees) as $index => $employee) {
                $this->giveDocuments($client, $employee, $index);
            }
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function employeesOf(ErpnextClient $client, string $company): array
    {
        return $client->list('Employee', ['name', 'employee_name'], [['company', '=', $company]], 50);
    }

    /** @return array<int, array<string, mixed>> */
    private function createSampleCrew(ErpnextClient $client, string $company, int $howMany): array
    {
        $created = [];

        foreach (array_slice(self::SAMPLE_CREW[$company] ?? [], 0, $howMany) as $person) {
            $row = $client->create('Employee', [
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name'],
                'gender' => $person['gender'],
                'date_of_birth' => $person['dob'],
                'date_of_joining' => now()->subYears(2)->toDateString(),
                'company' => $company,
                'status' => 'Active',
                'custom_rank' => $person['rank'],
                'custom_crew_status' => 'Standby',
                'custom_nationality' => 'Indonesian',
            ]);

            $this->info("  + Employee {$row['name']} — {$person['first_name']} {$person['last_name']}");
            $created[] = $row;
        }

        return $created;
    }

    /**
     * A realistic paper set: passport and seaman book always, medical and COC too,
     * with expiry dates staggered so Valid / Expiring Soon / Expired all show up.
     *
     * @param  array<string, mixed>  $employee
     */
    private function giveDocuments(ErpnextClient $client, array $employee, int $index): void
    {
        $name = $employee['name'];
        $document = $client->get('Employee', $name);

        if (! empty($document['custom_certificates']) && ! $this->option('force')) {
            $this->line("  = {$name} (sudah punya sertifikat)");

            return;
        }

        // Each crew gets a slightly different spread, so the lists are not uniform.
        $shift = $index * 15;

        $certificates = [
            $this->row('Passport', 'C' . (1000000 + $index * 7331), 'Imigrasi Jakarta Selatan', now()->subYears(3), now()->addYears(2)->addDays($shift)),
            $this->row('Seaman Book', 'SB-' . (200100 + $index * 37), 'Syahbandar Tanjung Priok', now()->subYears(2), now()->addDays(45 + $shift)),
            $this->row('Medical Certificate', 'MCU-' . (77120 + $index * 11), 'Klinik Pelaut Jakarta', now()->subMonths(11), now()->addDays(20 + $shift)),
            $this->row('Certificate of Competency', 'COC-' . (88450 + $index * 19), 'Ditjen Perhubungan Laut', now()->subYears(4), now()->addYears(1)->addDays($shift)),
            $this->row('Basic Safety Training', 'BST-' . (55010 + $index * 23), 'BP3IP Jakarta', now()->subYears(5), now()->subDays(30 - $shift)),
            $this->row('Yellow Fever', 'YF-' . (33020 + $index * 13), 'KKP Tanjung Priok', now()->subYears(2), now()->addYears(3)),
        ];

        $client->update('Employee', $name, ['custom_certificates' => $certificates]);

        $this->info("  + {$name} — " . count($certificates) . ' sertifikat');
    }

    /** @return array<string, string> */
    private function row(string $type, string $number, string $issuer, Carbon $issued, Carbon $expires): array
    {
        return [
            'certificate_type' => $type,
            'certificate_number' => $number,
            'issued_by' => $issuer,
            'issue_date' => $issued->toDateString(),
            'expiry_date' => $expires->toDateString(),
            'status' => match (true) {
                $expires->isPast() => 'Expired',
                $expires->lessThanOrEqualTo(now()->addDays(60)) => 'Expiring Soon',
                default => 'Valid',
            },
        ];
    }
}
