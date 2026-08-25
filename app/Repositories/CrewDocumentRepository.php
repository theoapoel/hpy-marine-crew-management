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
     * @param  array{type?: string, status?: string, search?: string, expiring_in?: int}  $filters  expiring_in is in months
     * @return Collection<int, object>
     */
    public function all(array $filters = []): Collection
    {
        $crew = $this->crewOfCompany();

        if ($crew->isEmpty()) {
            return collect();
        }

        $typeFilter = [];

        if ($type = $filters['type'] ?? null) {
            $typeFilter[] = ['certificate_type', '=', $type];
        }

        // A "parent in [...]" filter carrying crew ids travels in the GET query string,
        // and with long employee ids even a 60-id chunk blows past ERP HPY's reverse
        // proxy request-line limit (400 "Request Line is too Large"). So fetch the
        // certificate rows without naming parents at all — paged, with only the type
        // filter — and intersect with the company's crew here. The URL stays a constant,
        // short length no matter how many crew there are.
        $rows = collect();
        $pageSize = 500;

        for ($start = 0; ; $start += $pageSize) {
            $page = $this->client->list(
                self::CHILD_DOCTYPE,
                ['name', 'parent', 'certificate_type', 'certificate_number', 'issued_by', 'issue_date', 'expiry_date', 'status', 'attachment', 'remarks'],
                $typeFilter,
                $pageSize,
                $start,
                ['parent' => (string) config('services.erpnext.crew_doctype', 'Employee')],
            );

            $rows = $rows->merge($page);

            if (count($page) < $pageSize) {
                break;
            }
        }

        return $rows
            ->filter(fn (array $row) => $crew->has($row['parent']))
            ->map(function (array $row) use ($crew) {
                $row['crew_id'] = $row['parent'];
                $row['crew_name'] = $crew[$row['parent']]['employee_name'] ?? $row['parent'];
                $row['rank'] = $crew[$row['parent']]['custom_rank'] ?? null;
                $row['vessel'] = $crew[$row['parent']]['custom_vessel'] ?? null;
                $row['status'] = $this->status($row);
                $expiry = $row['expiry_date'] ? Carbon::parse($row['expiry_date'])->startOfDay() : null;
                $row['days_left'] = $expiry
                    ? (int) now()->startOfDay()->diffInDays($expiry, false)
                    : null;
                // Expiry is presented in whole months everywhere in the UI.
                $row['months_left'] = $expiry
                    ? (int) now()->startOfDay()->diffInMonths($expiry, false)
                    : null;

                return (object) $row;
            })
            ->when($filters['status'] ?? null, fn ($rows, $status) => $rows->where('status', $status))
            ->when(
                $filters['expiring_in'] ?? null,
                fn ($rows, $months) => $rows->filter(fn ($row) => $row->months_left !== null && $row->months_left < $months),
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
     * A single page would silently drop whoever fell past the limit once the fleet grew
     * past it — as it nearly did here — so this pages through the whole list instead of
     * trusting one call to return everyone.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function crewOfCompany(): Collection
    {
        $filters = [];

        if ($company = $this->client->company()) {
            $filters[] = ['company', '=', $company];
        }

        $crew = collect();
        $pageSize = 500;

        for ($start = 0; ; $start += $pageSize) {
            $page = $this->client->list(
                (string) config('services.erpnext.crew_doctype', 'Employee'),
                ['name', 'employee_name', 'custom_rank', 'custom_vessel'],
                $filters,
                $pageSize,
                $start,
            );

            $crew = $crew->merge(collect($page)->keyBy('name'));

            if (count($page) < $pageSize) {
                break;
            }
        }

        return $crew;
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
