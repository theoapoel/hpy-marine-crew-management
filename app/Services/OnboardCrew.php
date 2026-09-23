<?php

namespace App\Services;

use App\Models\CrewAssignment;
use App\Services\Erpnext\ErpnextClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who is aboard which ship, answered from both places a crew member can be put aboard.
 *
 * ERP HPY is the system of record: an Employee whose custom_crew_status is "Onboard"
 * is aboard custom_vessel, however they got there — a sign on here, Crew Master, or
 * the ERP desk. Crew Assignment adds the people ERP HPY does not know about yet (no
 * employee_id, or ERP HPY unreachable) and the contract details it carries. A person
 * is counted once: matched by employee_id, ERP HPY wins on vessel and rank.
 *
 * Every row has the same shape, so a view can list them without asking where they
 * came from: employee_id, crew_name, rank, vessel, sign_on_date, contract_until,
 * days_onboard, href.
 */
class OnboardCrew
{
    private const FIELDS = ['name', 'employee_name', 'custom_rank', 'designation', 'custom_vessel', 'custom_sign_on_date', 'contract_end_date'];

    private ?Collection $all = null;

    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    /** @return Collection<int, object> */
    public function all(): Collection
    {
        if ($this->all !== null) {
            return $this->all;
        }

        $local = CrewAssignment::ofCompany()->onboard()->get();
        $byEmployee = $local->filter(fn ($a) => filled($a->employee_id))->keyBy('employee_id');

        $fromErp = collect($this->employees([['custom_crew_status', '=', 'Onboard']]))
            ->map(function (array $row) use ($byEmployee) {
                $assignment = $byEmployee[$row['name']] ?? null;
                $signOn = $row['custom_sign_on_date'] ?? null;

                return $this->row(
                    employeeId: $row['name'],
                    name: $row['employee_name'] ?? $assignment?->crew_name ?? $row['name'],
                    rank: ($row['custom_rank'] ?? null) ?: ($row['designation'] ?? null) ?: $assignment?->rank,
                    vessel: ($row['custom_vessel'] ?? null) ?: $assignment?->vessel,
                    signOn: $signOn ? Carbon::parse($signOn) : $assignment?->sign_on_date,
                    until: $assignment?->planned_sign_off_date ?? (($row['contract_end_date'] ?? null) ? Carbon::parse($row['contract_end_date']) : null),
                    href: $assignment ? route('assignments.show', $assignment) : route('crew.show', $row['name']),
                );
            });

        $known = $fromErp->pluck('employee_id')->flip();

        $localOnly = $local
            ->reject(fn ($a) => filled($a->employee_id) && isset($known[$a->employee_id]))
            ->map(fn (CrewAssignment $a) => $this->row(
                employeeId: $a->employee_id,
                name: $a->crew_name,
                rank: $a->rank,
                vessel: $a->vessel,
                signOn: $a->sign_on_date,
                until: $a->planned_sign_off_date,
                href: route('assignments.show', $a),
            ));

        return $this->all = $fromErp->concat($localOnly)->values();
    }

    /** @return Collection<int, object> */
    public function onVessel(string $vessel): Collection
    {
        return $this->all()->where('vessel', $vessel)->sortBy('rank')->values();
    }

    /**
     * Every sign on since a date: the assignments' history, plus the sign on date ERP
     * HPY holds for crew who were boarded outside the app. One Employee only keeps
     * their latest sign on, so assignments are the only record of earlier voyages.
     *
     * @return Collection<int, Carbon>
     */
    public function signOnsSince(Carbon $from): Collection
    {
        $assignments = CrewAssignment::ofCompany()
            ->whereNotNull('sign_on_date')
            ->where('sign_on_date', '>=', $from)
            ->get(['employee_id', 'sign_on_date']);

        $recorded = $assignments
            ->filter(fn ($a) => filled($a->employee_id))
            ->map(fn ($a) => $a->employee_id . '|' . $a->sign_on_date->toDateString())
            ->flip();

        $fromErp = collect($this->employees([['custom_sign_on_date', '>=', $from->toDateString()]]))
            ->reject(fn ($row) => isset($recorded[$row['name'] . '|' . Carbon::parse($row['custom_sign_on_date'])->toDateString()]))
            ->map(fn ($row) => Carbon::parse($row['custom_sign_on_date']));

        return $assignments->pluck('sign_on_date')->concat($fromErp)->values();
    }

    /**
     * Employees of the session company matching the filters. ERP HPY being down must
     * not take the page with it — the local assignments are still worth showing.
     *
     * @param  array<int, array<int, mixed>>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function employees(array $filters): array
    {
        if (! $this->erpnext->isConfigured()) {
            return [];
        }

        if ($company = $this->erpnext->company()) {
            $filters[] = ['company', '=', $company];
        }

        try {
            return $this->erpnext->list((string) config('services.erpnext.crew_doctype', 'Employee'), self::FIELDS, $filters, 2000);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function row(?string $employeeId, ?string $name, ?string $rank, ?string $vessel, ?Carbon $signOn, ?Carbon $until, string $href): object
    {
        return (object) [
            'employee_id' => $employeeId,
            'crew_name' => (string) $name,
            'rank' => $rank ?: null,
            'vessel' => $vessel ?: null,
            'sign_on_date' => $signOn,
            'contract_until' => $until,
            'days_onboard' => $signOn ? (int) $signOn->diffInDays(now()) : null,
            'href' => $href,
        ];
    }
}
