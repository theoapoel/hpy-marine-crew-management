<?php

namespace App\Http\Controllers;

use App\Models\Crew;
use App\Models\Vessel;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $vessels = Vessel::withCount(['crews' => fn ($q) => $q->where('status', 'Onboard')])->get();

        $activeCrew = Crew::where('status', 'Onboard')->count();
        $atSea = $vessels->where('status', 'At Sea')->count();
        $inPort = $vessels->where('status', 'In Port')->count();
        $dryDock = $vessels->where('status', 'Dry Dock')->count();

        $expiringSoon = Crew::whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [now(), now()->addDays(30)])
            ->count();

        $stats = [
            ['label' => 'Active Crew', 'value' => (string) $activeCrew, 'delta' => 'Onboard', 'sub' => 'across ' . $vessels->count() . ' vessels'],
            ['label' => 'Fleet Size', 'value' => (string) $vessels->count(), 'delta' => 'Fleet', 'sub' => "{$atSea} at sea · {$inPort} in port · {$dryDock} drydock"],
            ['label' => 'Contracts Expiring', 'value' => (string) $expiringSoon, 'delta' => '30d', 'sub' => 'within 30 days'],
            ['label' => 'Standby Crew', 'value' => (string) Crew::where('status', 'Standby')->count(), 'delta' => 'Pool', 'sub' => 'available for assignment'],
        ];

        // Crew by rank
        $rankCounts = Crew::selectRaw('rank, count(*) as total')->groupBy('rank')->pluck('total', 'rank');
        $totalCrew = max($rankCounts->sum(), 1);
        $ranks = collect(['Master', 'Officers', 'Engineers', 'Ratings', 'Catering'])
            ->map(fn ($rank) => [
                'name' => $rank,
                'count' => $rankCounts[$rank] ?? 0,
                'pct' => round((($rankCounts[$rank] ?? 0) / $totalCrew) * 100),
            ])->all();

        // Fleet status table
        $fleet = $vessels->map(fn ($v) => [
            'vessel' => $v->name,
            'type' => $v->type,
            'status' => $v->status,
            'crew' => $v->crews_count . '/' . $v->crew_capacity,
            'principal' => $v->principal ?? '—',
            'eta' => $v->eta ?? '—',
        ])->all();

        // Recent activity from newest crew records
        $activity = Crew::with('vessel')->latest()->take(6)->get()->map(function ($crew) {
            return match ($crew->status) {
                'Onboard' => ['title' => "{$crew->name} onboard {$crew->vessel?->name}", 'sub' => $crew->rank, 'time' => $crew->created_at->diffForHumans(), 'type' => 'signon'],
                'Sign Off' => ['title' => "{$crew->name} signed off {$crew->vessel?->name}", 'sub' => $crew->rank, 'time' => $crew->created_at->diffForHumans(), 'type' => 'signoff'],
                default => ['title' => "{$crew->name} on standby", 'sub' => $crew->rank, 'time' => $crew->created_at->diffForHumans(), 'type' => 'warn'],
            };
        })->all();

        return view('dashboard', [
            'stats' => $stats,
            'ranks' => $ranks,
            'fleet' => $fleet,
            'activity' => $activity,
            'today' => Carbon::today()->format('l, F j, Y'),
        ]);
    }
}
