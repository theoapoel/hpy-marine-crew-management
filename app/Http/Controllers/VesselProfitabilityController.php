<?php

namespace App\Http\Controllers;

use App\Services\Erpnext\VesselProfitability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Three screens over one question — is this ship making money?
 *
 *   index    the fleet, worst margin last
 *   vessel   one ship, its projects, and where the money goes
 *   project  one contract, down to the invoices behind every component
 *
 * All read-only, all from ERP HPY, all scoped to the company in the header.
 * See App\Services\Erpnext\VesselProfitability for where the numbers come from.
 */
class VesselProfitabilityController extends Controller
{
    public function __construct(private readonly VesselProfitability $profitability)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('profitability.viewAny');

        $window = $this->window($request);

        return view('profitability.index', $this->profitability->fleet($window) + [
            'window' => $window,
        ]);
    }

    public function vessel(Request $request, string $vessel)
    {
        Gate::authorize('profitability.view');

        $window = $this->window($request);

        return view('profitability.vessel', $this->profitability->vessel($vessel, $window) + [
            'window' => $window,
        ]);
    }

    public function project(string $project)
    {
        Gate::authorize('profitability.view');

        return view('profitability.project', $this->profitability->project($project));
    }

    /**
     * The reporting period. Both ends are optional — an empty form means everything
     * ever posted, which is the right default for a ship that runs for years.
     *
     * @return array{from: ?string, to: ?string}
     */
    private function window(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return [
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];
    }
}
