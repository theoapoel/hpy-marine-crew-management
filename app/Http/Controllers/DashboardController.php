<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Repositories\CrewDocumentRepository;
use App\Services\Erpnext\ErpnextClient;
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
    ) {
    }

    public function index()
    {
        $vessels = $this->vessels();
        $onboard = CrewAssignment::ofCompany()->onboard()->get();
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
     * fixed list, so a rank nobody holds does not take up a row.
     *
     * @param  Collection<int, CrewAssignment>  $onboard
     * @return array<int, array<string, mixed>>
     */
    private function ranks(Collection $onboard): array
    {
        $counts = $onboard->countBy(fn ($a) => $a->rank ?: 'Unspecified')->sortDesc()->take(6);
        $highest = max($counts->first() ?? 0, 1);

        return $counts->map(fn ($count, $rank) => [
            'name' => $rank,
            'count' => $count,
            'pct' => round(($count / $highest) * 100),
        ])->values()->all();
    }

    /**
     * Manning per ship, emptiest first: a vessel with nobody aboard is the one that
     * needs looking at, so it must not sit at the bottom of the table.
     *
     * @param  Collection<int, array<string, mixed>>  $vessels
     * @param  Collection<int, CrewAssignment>  $onboard
     * @param  Collection<int, CrewAssignment>  $planned
     * @return array<int, array<string, mixed>>
     */
    private function fleet(Collection $vessels, Collection $onboard, Collection $planned): array
    {
        $aboard = $onboard->countBy('vessel');
        $booked = $planned->countBy('vessel');

        return $vessels->map(fn (array $v) => [
            'name' => $v['name'],
            'vessel' => $v['vessel_name'] ?? $v['name'],
            'type' => $v['vessel_type'] ?? '—',
            'principal' => $v['principal'] ?? '—',
            'onboard' => $aboard[$v['name']] ?? 0,
            'planned' => $booked[$v['name']] ?? 0,
        ])->sortBy([['onboard', 'asc'], ['vessel', 'asc']])->values()->all();
    }
}
