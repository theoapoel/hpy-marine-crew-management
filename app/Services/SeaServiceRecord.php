<?php

namespace App\Services;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Collection;

/**
 * A seafarer's sea service record, as printed on a CV: every berth they have held
 * through us, latest first, with the ship's particulars beside it.
 *
 * The berths are our own Crew Assignment rows (onboard or signed off; a planned one
 * is not yet service). The ship name, type, GRT and owner come from the Vessel
 * doctype in ERP HPY, so the record reads the same as the fleet pages. If ERP HPY is
 * unreachable the rows still list, with only the vessel columns left blank.
 */
class SeaServiceRecord
{
    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    /**
     * @return Collection<int, CrewAssignment>  each with a `vessel_details` array attached
     */
    public function for(string $employeeId): Collection
    {
        $berths = CrewAssignment::ofCompany()
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['onboard', 'signed_off'])
            ->orderByRaw('COALESCE(sign_on_date, planned_sign_on_date) DESC')
            ->orderByDesc('id')
            ->get();

        $vessels = $this->vessels($berths->pluck('vessel')->filter()->unique()->values()->all());

        return $berths->each(function (CrewAssignment $berth) use ($vessels) {
            $berth->setAttribute('vessel_details', $vessels[$berth->vessel] ?? []);
        });
    }

    /**
     * The berths plus the sea service typed in by hand on the crew record (contracts
     * with other companies, or from before we kept assignments), as one list, latest
     * first. Hand-entered rows are unsaved models flagged `manual`, shaped like a berth
     * so the views print both the same way.
     *
     * @param  Collection<int, CrewAssignment>  $berths
     * @param  iterable<int, object|array<string, mixed>>  $manual
     * @return Collection<int, CrewAssignment>
     */
    public function withManual(Collection $berths, iterable $manual): Collection
    {
        $rows = collect($manual)->map(function ($row) {
            $row = (array) $row;

            return (new CrewAssignment)->forceFill([
                'manual' => true,
                'rank' => $row['rank'] ?? null,
                'vessel' => $row['vessel_name'] ?? null,
                'company' => $row['company_name'] ?? null,
                'sign_on_date' => $row['sign_on_date'] ?? null,
                'sign_off_date' => $row['sign_off_date'] ?? null,
                'appraisal' => $row['appraisal'] ?? null,
                'appraisal_notes' => $row['remarks'] ?? null,
                'vessel_details' => array_filter([
                    'vessel_name' => $row['vessel_name'] ?? null,
                    'principal' => $row['company_name'] ?? null,
                    'vessel_type' => $row['vessel_type'] ?? null,
                    'gross_tonnage' => $row['gross_tonnage'] ?? null,
                ], fn ($v) => filled($v)),
            ]);
        });

        return $berths->toBase()->concat($rows)
            ->sortByDesc(fn (CrewAssignment $b) => $b->sign_on_date?->timestamp ?? 0)
            ->values();
    }

    /**
     * Ship particulars keyed by Vessel name.
     *
     * @param  array<int, string>  $names
     * @return array<string, array<string, mixed>>
     */
    private function vessels(array $names): array
    {
        if ($names === []) {
            return [];
        }

        try {
            $rows = $this->erpnext->list(
                'Vessel',
                ['name', 'vessel_name', 'vessel_type', 'gross_tonnage', 'principal'],
                [['name', 'in', $names]],
                count($names),
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return collect($rows)->keyBy('name')->all();
    }
}
