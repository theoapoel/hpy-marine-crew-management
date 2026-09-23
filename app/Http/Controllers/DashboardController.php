<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Repositories\CrewDocumentRepository;
use App\Services\Erpnext\ErpnextClient;
use App\Services\OnboardCrew;
use App\Support\Departments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The opening screen answers three questions a crewing officer asks every morning:
 * who is aboard, whose papers run out next, and who moves this week.
 *
 * Everything here reads the same sources as the pages it links to — the fleet from
 * ERP HPY, crew movements from Crew Assignment — so the numbers cannot drift from
 * the lists behind them, and all of it follows the company picked in the header.
 */
class DashboardController extends Controller
{
    /** How far ahead the document and contract widgets look. */
    private const HORIZON = 90;

    public function __construct(
        private readonly ErpnextClient $erpnext,
        private readonly CrewDocumentRepository $documents,
        private readonly OnboardCrew $crew,
    ) {
    }

    public function index()
    {
        $vessels = $this->vessels();
        // Aboard per ERP HPY (Crew Master) and per our own assignments, each person once.
        $onboard = $this->crew->all();
        $planned = CrewAssignment::ofCompany()->planned()->get();
        $expiring = $this->expiringDocuments();

        $manned = $onboard->pluck('vessel')->filter()->unique();
        $dueForSignOff = CrewAssignment::ofCompany()->dueForSignOff(30)->count();
        $pool = CrewCandidate::ofCompany()->whereIn('status', ['applicant', 'in_process'])->count();

        $stats = [
            [
                'label' => 'Crew Onboard',
                'value' => (string) $onboard->count(),
                'delta' => 'Onboard',
                'sub' => $planned->count() . ' planned to join',
                'href' => route('assignments.index'),
            ],
            [
                'label' => 'Fleet Size',
                'value' => (string) $vessels->count(),
                'delta' => 'Fleet',
                'sub' => $manned->count() . ' manned · ' . max($vessels->count() - $manned->count(), 0) . ' without crew',
                'href' => route('vessels.index'),
            ],
            [
                'label' => 'Contracts Ending',
                'value' => (string) $dueForSignOff,
                'delta' => '30d',
                'sub' => 'sign off to plan',
                'href' => route('assignments.sign-off'),
            ],
            [
                'label' => 'Talent Pool',
                'value' => (string) $pool,
                'delta' => 'Pool',
                'sub' => 'available for assignment',
                'href' => route('candidates.index'),
            ],
        ];

        return view('dashboard', [
            'stats' => $stats,
            'ranks' => $this->ranks($onboard),
            'fleet' => $this->fleet($vessels, $onboard, $planned),
            'signOns' => $this->monthlySignOns(),
            'documents' => $expiring,
            'movements' => $this->movementsThisWeek(),
            'today' => Carbon::today()->translatedFormat('l, j F Y'),
            'user' => (string) session(ErpnextClient::SESSION_KEY . '.full_name'),
        ]);
    }

    /**
     * The fleet of the session company. A dead API session must not take the whole
     * dashboard down — the local widgets are still worth showing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function vessels(): Collection
    {
        $filters = [];

        if ($company = $this->erpnext->company()) {
            $filters[] = ['company', '=', $company];
        }

        try {
            return collect($this->erpnext->list('Vessel', ['name', 'vessel_name', 'vessel_type', 'principal'], $filters, 200));
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Certificates, passports and seaman books running out, split into the buckets a
     * crewing officer works in: already expired first, then 30 / 60 / 90 days.
     *
     * @return array<string, mixed>
     */
    private function expiringDocuments(): array
    {
        try {
            $rows = $this->documents->all(['expiring_in' => self::HORIZON]);
        } catch (\Throwable $e) {
            report($e);

            $rows = collect();
        }

        $within = fn (int $from, int $to) => $rows->filter(
            fn ($row) => $row->days_left >= $from && $row->days_left <= $to,
        )->count();

        return [
            'buckets' => [
                ['label' => 'Expired', 'count' => $rows->filter(fn ($r) => $r->days_left < 0)->count(), 'tone' => 'rose'],
                ['label' => '≤ 30 days', 'count' => $within(0, 30), 'tone' => 'amber'],
                ['label' => '≤ 60 days', 'count' => $within(31, 60), 'tone' => 'sky'],
                ['label' => '≤ 90 days', 'count' => $within(61, self::HORIZON), 'tone' => 'slate'],
            ],
            // Soonest first, so the top of the list is the next thing to chase.
            'soonest' => $rows->sortBy('days_left')->take(5)->values(),
            'total' => $rows->count(),
        ];
    }

    /**
     * Who joins and who leaves between now and the end of the week — the two desks,
     * side by side, before anyone has to open them.
     *
     * @return array<string, Collection<int, CrewAssignment>>
     */
    private function movementsThisWeek(): array
    {
        $until = Carbon::today()->endOfWeek();

        return [
            'until' => $until,
            // Overdue moves stay on the list rather than dropping off it silently.
            'joining' => CrewAssignment::ofCompany()->planned()
                ->whereNotNull('planned_sign_on_date')
                ->where('planned_sign_on_date', '<=', $until)
                ->orderBy('planned_sign_on_date')
                ->get(),
            'leaving' => CrewAssignment::ofCompany()->onboard()
                ->whereNotNull('planned_sign_off_date')
                ->where('planned_sign_off_date', '<=', $until)
                ->orderBy('planned_sign_off_date')
                ->get(),
        ];
    }

    /**
     * Ranks actually sailing, biggest first — read off the assignments rather than a
     * fixed list, so a rank nobody holds does not take up a row. Past ten ranks the
     * tail folds into "Other" instead of stretching the chart.
     *
     * @param  Collection<int, object>  $onboard  rows from OnboardCrew
     * @return array<int, array{name: string, count: int, department: string}>
     */
    private function ranks(Collection $onboard): array
    {
        $counts = $onboard->countBy(fn ($a) => $a->rank ?: 'Unspecified')->sortDesc();

        $rows = $counts->take(10)->map(fn ($count, $rank) => [
            'name' => (string) $rank,
            'count' => $count,
            'department' => Departments::of((string) $rank),
        ])->values();

        if ($counts->count() > 10) {
            $rows->push(['name' => 'Other (' . ($counts->count() - 10) . ' ranks)', 'count' => $counts->slice(10)->sum(), 'department' => 'Other']);
        }

        return $rows->all();
    }

    /**
     * Crew who actually signed on, per month, for the last twelve months — months
     * with nobody joining included, so the gaps show. Counts sign ons recorded here
     * and those made in ERP HPY directly (see OnboardCrew::signOnsSince).
     *
     * @return array<int, array{key: string, label: string, full: string, count: int}>
     */
    private function monthlySignOns(): array
    {
        $from = Carbon::today()->startOfMonth()->subMonths(11);

        $counts = $this->crew->signOnsSince($from)
            ->countBy(fn (Carbon $date) => $date->format('Y-m'));

        return collect(range(0, 11))->map(function (int $i) use ($from, $counts) {
            $month = $from->copy()->addMonths($i);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M'),
                'full' => $month->translatedFormat('F Y'),
                'count' => $counts[$month->format('Y-m')] ?? 0,
            ];
        })->all();
    }

    /**
     * Manning per ship, most crew first, as the chart reads it. Ships with nobody
     * aboard still get a row (flagged "no crew"), so an empty vessel is never missing.
     *
     * @param  Collection<int, array<string, mixed>>  $vessels
     * @param  Collection<int, object>  $onboard  rows from OnboardCrew
     * @param  Collection<int, CrewAssignment>  $planned
     * @return array<int, array<string, mixed>>
     */
    private function fleet(Collection $vessels, Collection $onboard, Collection $planned): array
    {
        $aboard = $onboard->countBy('vessel');
        $booked = $planned->countBy('vessel');
        $crews = $onboard->groupBy('vessel');

        return $vessels->map(fn (array $v) => [
            'name' => $v['name'],
            'vessel' => $v['vessel_name'] ?? $v['name'],
            'type' => $v['vessel_type'] ?? '—',
            'principal' => $v['principal'] ?? '—',
            'onboard' => $aboard[$v['name']] ?? 0,
            'planned' => $booked[$v['name']] ?? 0,
            // Onboard split by department, in the chart's fixed colour order.
            'departments' => collect(array_keys(Departments::COLORS))
                ->mapWithKeys(fn ($d) => [$d => ($crews[$v['name']] ?? collect())->filter(fn ($a) => Departments::of($a->rank) === $d)->count()])
                ->filter()
                ->all(),
        ])->sortBy([['onboard', 'desc'], ['vessel', 'asc']])->values()->all();
    }
}
