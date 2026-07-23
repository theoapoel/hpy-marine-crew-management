<?php

namespace App\Repositories;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Documents menu is a fleet-wide view of the certificate rows that hang off each
 * crew member (the "Employee Certificate" child table), scoped to the session company.
 */
class CrewDocumentRepository
{
    private const CHILD_DOCTYPE = 'Employee Certificate';

    public function __construct(private readonly ErpnextClient $client)
    {
    }

    /**
     * @param  array{type?: string, status?: string, search?: string, expiring_in?: int}  $filters
     * @return Collection<int, object>
     */
    public function all(array $filters = []): Collection
    {
        $crew = $this->crewOfCompany();

        if ($crew->isEmpty()) {
            return collect();
        }

        $rowFilters = [['parent', 'in', $crew->keys()->all()]];

        if ($type = $filters['type'] ?? null) {
            $rowFilters[] = ['certificate_type', '=', $type];
        }

        $rows = $this->client->listChildren(
            self::CHILD_DOCTYPE,
            (string) config('services.erpnext.crew_doctype', 'Employee'),
            ['name', 'parent', 'certificate_type', 'certificate_number', 'issued_by', 'issue_date', 'expiry_date', 'status', 'attachment', 'remarks'],
            $rowFilters,
        );

        return collect($rows)
            ->map(function (array $row) use ($crew) {
                $row['crew_id'] = $row['parent'];
                $row['crew_name'] = $crew[$row['parent']]['employee_name'] ?? $row['parent'];
                $row['rank'] = $crew[$row['parent']]['custom_rank'] ?? null;
                $row['vessel'] = $crew[$row['parent']]['custom_vessel'] ?? null;
                $row['status'] = $this->status($row);
                $row['days_left'] = $row['expiry_date']
                    ? (int) now()->startOfDay()->diffInDays(Carbon::parse($row['expiry_date'])->startOfDay(), false)
                    : null;

                return (object) $row;
            })
            ->when($filters['status'] ?? null, fn ($rows, $status) => $rows->where('status', $status))
            ->when(
                $filters['expiring_in'] ?? null,
                fn ($rows, $days) => $rows->filter(fn ($row) => $row->days_left !== null && $row->days_left <= $days),
            )
            ->when(
                trim((string) ($filters['search'] ?? '')),
                fn ($rows, $search) => $rows->filter(
                    fn ($row) => str_contains(mb_strtolower($row->crew_name . ' ' . $row->certificate_number), mb_strtolower($search)),
                ),
            )
            ->sortBy(fn ($row) => $row->expiry_date ?: '9999-12-31')
            ->values();
    }

    /**
     * Crew of the session company, keyed by ERP HPY name.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function crewOfCompany(): Collection
    {
        $filters = [];

        if ($company = $this->client->company()) {
            $filters[] = ['company', '=', $company];
        }

        return collect($this->client->list(
            (string) config('services.erpnext.crew_doctype', 'Employee'),
            ['name', 'employee_name', 'custom_rank', 'custom_vessel'],
            $filters,
            500,
        ))->keyBy('name');
    }

    /** Recomputed on read so a stored "Valid" does not outlive the expiry date. */
    private function status(array $row): string
    {
        if (($row['status'] ?? null) === 'Revoked') {
            return 'Revoked';
        }

        if (blank($row['expiry_date'] ?? null)) {
            return $row['status'] ?: 'Valid';
        }

        $expiry = Carbon::parse($row['expiry_date'])->startOfDay();

        return match (true) {
            $expiry->isPast() => 'Expired',
            $expiry->lessThanOrEqualTo(now()->addDays(60)) => 'Expiring Soon',
            default => 'Valid',
        };
    }
}
