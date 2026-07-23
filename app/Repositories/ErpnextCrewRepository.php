<?php

namespace App\Repositories;

use App\Models\Crew;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Request;

/**
 * ERP HPY-backed crew repository. Crew Master reads from and writes to the Doctype
 * "Employee" (overridable via ERPNEXT_CREW_DOCTYPE), always scoped to the company the
 * session picked.
 *
 * The form mirrors the Employee doctype itself: the standard HR fields plus the
 * marine-specific custom fields and the "Employee Certificate" child table created by
 * `php artisan erp:sync-crew-fields`.
 *
 * Returns non-persisted Crew models so Blade views keep working unchanged.
 */
class ErpnextCrewRepository implements CrewRepositoryInterface
{
    /** Standard Employee fields the app reads and writes as-is. */
    private const PLAIN_FIELDS = [
        'salutation', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
        'date_of_joining', 'department', 'designation', 'branch', 'employee_number',
        'cell_number', 'personal_email', 'company_email', 'current_address',
        'permanent_address', 'person_to_be_contacted', 'emergency_phone_number', 'relation',
        'marital_status', 'blood_group', 'health_details', 'passport_number', 'date_of_issue',
        'valid_upto', 'place_of_issue', 'bank_name', 'bank_ac_no', 'contract_end_date',
    ];

    /** Marine-specific custom fields, keyed by the name the app uses. */
    private const CUSTOM_FIELDS = [
        'crew_status' => 'custom_crew_status',
        'nationality' => 'custom_nationality',
        'rank' => 'custom_rank',
        'seaman_book_no' => 'custom_seaman_book_no',
        'seaman_book_expiry' => 'custom_seaman_book_expiry',
        'vessel' => 'custom_vessel',
        'sign_on_date' => 'custom_sign_on_date',
        // contract_end_date is a standard Employee field, see PLAIN_FIELDS.
    ];

    /** Columns the crew list needs; a full document is fetched for the detail views. */
    private const LIST_FIELDS = [
        'name', 'employee_name', 'status', 'company', 'designation', 'image',
        'custom_rank', 'custom_crew_status', 'custom_nationality',
        'custom_seaman_book_no', 'custom_vessel', 'custom_sign_on_date',
        'contract_end_date',
    ];

    /** Crew statuses that mean the person still works for HPY. */
    private const ACTIVE_STATUSES = ['Onboard', 'Standby'];

    public function __construct(private readonly ErpnextClient $client)
    {
    }

    private function doctype(): string
    {
        return (string) config('services.erpnext.crew_doctype', 'Employee');
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $erpFilters = [];

        // Crew Master only ever shows the company the session is working in.
        if ($company = $this->client->company()) {
            $erpFilters[] = ['company', '=', $company];
        }
        if ($status = $filters['status'] ?? null) {
            $erpFilters[] = ['custom_crew_status', '=', $status];
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $erpFilters[] = ['employee_name', 'like', "%{$search}%"];
        }

        $page = max(1, (int) Request::query('page', 1));
        $start = ($page - 1) * $perPage;

        $rows = $this->client->list($this->doctype(), self::LIST_FIELDS, $erpFilters, $perPage, $start);
        $items = collect($rows)->map(fn ($row) => $this->hydrate($row));

        // ERP HPY REST does not return a total count here; approximate for the paginator.
        $total = $start + $items->count() + ($items->count() === $perPage ? 1 : 0);

        return new Paginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => Request::query(),
        ]);
    }

    public function find(int|string $id): Crew
    {
        return $this->hydrate($this->client->get($this->doctype(), (string) $id));
    }

    public function create(array $data): Crew
    {
        $row = $this->client->create($this->doctype(), $this->toErp($data, creating: true));

        return $this->afterWrite($row, $data);
    }

    public function update(int|string $id, array $data): Crew
    {
        $row = $this->client->update($this->doctype(), (string) $id, $this->toErp($data));

        return $this->afterWrite($row, $data);
    }

    public function delete(int|string $id): void
    {
        $this->client->delete($this->doctype(), (string) $id);
    }

    /**
     * Certificate files can only be attached once the Employee has a name, so uploads
     * happen after the document itself is written, then the rows are saved again with
     * the resulting file urls.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     */
    private function afterWrite(array $row, array $data): Crew
    {
        $name = (string) ($row['name'] ?? '');
        $patch = [];

        if (($photo = $data['photo'] ?? null) instanceof UploadedFile) {
            $patch['image'] = $this->client->upload(
                (string) $photo->get(),
                $photo->getClientOriginalName(),
                $this->doctype(),
                $name,
            );
        }

        if (($certificates = $this->certificateRows($data, $name)) !== null) {
            $patch['custom_certificates'] = $certificates;
        }

        if ($patch !== []) {
            $row = $this->client->update($this->doctype(), $name, $patch);
        }

        return $this->hydrate($row);
    }

    /**
     * Build the child table rows, uploading any newly picked file.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>|null  null when the form carried no certificate section
     */
    private function certificateRows(array $data, string $docname): ?array
    {
        if (! array_key_exists('certificates', $data)) {
            return null;
        }

        $rows = [];

        foreach ((array) $data['certificates'] as $certificate) {
            $type = trim((string) ($certificate['certificate_type'] ?? ''));

            if ($type === '') {
                continue; // blank repeater row
            }

            $file = $certificate['file'] ?? null;
            $attachment = $certificate['attachment'] ?? null;

            if ($file instanceof UploadedFile) {
                $attachment = $this->client->upload(
                    (string) $file->get(),
                    $file->getClientOriginalName(),
                    $this->doctype(),
                    $docname,
                );
            }

            $rows[] = array_filter([
                'certificate_type' => $type,
                'certificate_number' => $certificate['certificate_number'] ?? null,
                'issued_by' => $certificate['issued_by'] ?? null,
                'issue_date' => $certificate['issue_date'] ?? null,
                'expiry_date' => $certificate['expiry_date'] ?? null,
                'status' => $certificate['status'] ?? $this->certificateStatus($certificate['expiry_date'] ?? null),
                'attachment' => $attachment,
                'remarks' => $certificate['remarks'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $rows;
    }

    /** Mirrors how the instance's own Vessel Certificate table reads: valid / expiring / expired. */
    private function certificateStatus(?string $expiry): string
    {
        if (! $expiry) {
            return 'Valid';
        }

        $date = \Illuminate\Support\Carbon::parse($expiry);

        return match (true) {
            $date->isPast() => 'Expired',
            $date->lessThanOrEqualTo(now()->addDays(60)) => 'Expiring Soon',
            default => 'Valid',
        };
    }

    /**
     * Map an ERP HPY Employee record into a (non-persisted) Crew model.
     *
     * @param  array<string, mixed>  $row
     */
    private function hydrate(array $row): Crew
    {
        $crew = new Crew();
        // ERP HPY names ("HR-EMP-00001") are strings, not auto-increment ints.
        $crew->incrementing = false;

        $attributes = ['id' => $row['name'] ?? null, 'name' => $row['employee_name'] ?? ($row['name'] ?? '')];

        foreach (self::PLAIN_FIELDS as $field) {
            $attributes[$field] = $row[$field] ?? null;
        }

        foreach (self::CUSTOM_FIELDS as $app => $erp) {
            $attributes[$app] = $row[$erp] ?? null;
        }

        // Views (and the old SQLite schema) call the crew status simply "status".
        $attributes['status'] = $row['custom_crew_status'] ?? null;
        $attributes['employee_status'] = $row['status'] ?? null;
        $attributes['company'] = $row['company'] ?? null;
        $attributes['image'] = $row['image'] ?? null;
        $attributes['rank'] = $row['custom_rank'] ?? ($row['designation'] ?? null);

        $crew->forceFill($attributes);
        $crew->exists = true;

        $crew->certificates = collect($row['custom_certificates'] ?? [])
            ->map(fn ($c) => (object) $c)
            ->all();

        return $crew;
    }

    /**
     * Map app form data back to ERP HPY Employee fields.
     *
     * employee_name is composed by ERP HPY from first/last name, so the name is split
     * instead of sent as-is. The crew status (Onboard/Standby/Sign Off) is not a valid
     * Employee status, so it rides in a custom field while Employee.status only records
     * whether the person is still with the company.
     *
     * Only what the caller actually passed is sent. A partial update — the crew
     * assignment mirroring a posting, say — must not blank out the name or move the
     * joining date, and ERP HPY rejects an empty first_name outright.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toErp(array $data, bool $creating = false): array
    {
        $payload = [];

        if ($creating) {
            $payload['company'] = $this->client->company();
            $payload['date_of_joining'] = $data['date_of_joining'] ?? $data['sign_on_date'] ?? now()->toDateString();
        } elseif (array_key_exists('date_of_joining', $data)) {
            $payload['date_of_joining'] = $data['date_of_joining'];
        }

        if ($creating || array_key_exists('name', $data) || array_key_exists('first_name', $data)) {
            [$firstName, $lastName] = $this->splitName((string) ($data['name'] ?? ''));

            $payload['first_name'] = $data['first_name'] ?? $firstName;
            $payload['last_name'] = $data['last_name'] ?? $lastName;
        }

        if ($creating || array_key_exists('status', $data)) {
            $active = in_array($data['status'] ?? null, self::ACTIVE_STATUSES, true);

            $payload['status'] = $active ? 'Active' : 'Left';
            // ERP HPY requires a relieving date once an Employee is marked Left.
            $payload['relieving_date'] = $active ? null : ($data['contract_end_date'] ?? now()->toDateString());
            $payload['custom_crew_status'] = $data['status'] ?? null;
        }

        foreach (self::PLAIN_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        foreach (self::CUSTOM_FIELDS as $app => $erp) {
            if (array_key_exists($app, $data)) {
                $payload[$erp] = $data[$app];
            }
        }

        // Only nulls are dropped: an empty string is how a field gets cleared
        // (signing off empties the vessel, for instance).
        return array_filter($payload, fn ($v) => $v !== null);
    }

    /** @return array{0: string, 1: ?string} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [''];

        return [$parts[0] ?? '', $parts[1] ?? null];
    }
}
