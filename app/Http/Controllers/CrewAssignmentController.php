<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Repositories\CrewRepositoryInterface;
use App\Services\Erpnext\ErpnextClient;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Crew Assignment, plus the Sign On and Sign Off desks that work the same records:
 * Sign On lists what is planned, Sign Off lists who is aboard.
 *
 * Both moves write back to the Employee in ERP HPY, so Crew Master keeps telling the
 * truth about who sails on what.
 */
class CrewAssignmentController extends Controller
{
    public function __construct(
        private readonly ErpnextOptions $options,
        private readonly ErpnextClient $erpnext,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('assignments.viewAny');

        $filters = $request->only('search', 'status', 'vessel', 'rank');

        return view('assignments.index', [
            'assignments' => $this->query($filters)->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => CrewAssignment::STATUSES,
            'vessels' => $this->options->vessels(),
            'ranks' => $this->options->ranks(),
        ]);
    }

    /** Sign On desk: everyone waiting to board. */
    public function signOnDesk(Request $request)
    {
        Gate::authorize('assignments.viewAny');

        return view('assignments.sign-on', [
            'assignments' => $this->query($request->only('search', 'vessel'))
                ->planned()
                ->orderBy('planned_sign_on_date')
                ->get(),
            'filters' => $request->only('search', 'vessel'),
            'vessels' => $this->options->vessels(),
        ]);
    }

    /** Sign Off desk: everyone aboard, soonest contract end first. */
    public function signOffDesk(Request $request)
    {
        Gate::authorize('assignments.viewAny');

        $days = (int) $request->query('days', 0);

        return view('assignments.sign-off', [
            'assignments' => $this->query($request->only('search', 'vessel'))
                ->onboard()
                ->when($days > 0, fn ($q) => $q->dueForSignOff($days))
                ->orderBy('planned_sign_off_date')
                ->get(),
            'filters' => $request->only('search', 'vessel') + ['days' => $days],
            'vessels' => $this->options->vessels(),
        ]);
    }

    public function create()
    {
        Gate::authorize('assignments.create');

        return view('assignments.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        Gate::authorize('assignments.create');

        $assignment = CrewAssignment::create($this->validated($request));

        return redirect()->route('assignments.show', $assignment)
            ->with('success', "{$assignment->assignment_code} dibuat.");
    }

    public function show(CrewAssignment $assignment)
    {
        Gate::authorize('assignments.view');

        return view('assignments.show', ['assignment' => $assignment->load('candidate', 'application')]);
    }

    public function edit(CrewAssignment $assignment)
    {
        Gate::authorize('assignments.update');

        return view('assignments.edit', $this->formOptions() + ['assignment' => $assignment]);
    }

    public function update(Request $request, CrewAssignment $assignment)
    {
        Gate::authorize('assignments.update');

        $assignment->update($this->validated($request));

        return redirect()->route('assignments.show', $assignment)
            ->with('success', "{$assignment->assignment_code} diperbarui.");
    }

    public function destroy(CrewAssignment $assignment)
    {
        Gate::authorize('assignments.delete');

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', "{$assignment->assignment_code} dihapus.");
    }

    public function signOn(Request $request, CrewAssignment $assignment)
    {
        Gate::authorize('assignments.update');

        abort_unless($assignment->status === 'planned', 422);

        $assignment->signOn($request->validate([
            'sign_on_date' => ['nullable', 'date'],
            'sign_on_port' => ['nullable', 'string', 'max:100'],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'planned_sign_off_date' => ['nullable', 'date', 'after:sign_on_date'],
        ]));

        return back()->with('success', "{$assignment->crew_name} sign on ke {$assignment->vessel}.");
    }

    public function signOff(Request $request, CrewAssignment $assignment)
    {
        Gate::authorize('assignments.update');

        abort_unless($assignment->status === 'onboard', 422);

        $assignment->signOff($request->validate([
            'sign_off_date' => ['nullable', 'date'],
            'sign_off_port' => ['nullable', 'string', 'max:100'],
            'sign_off_reason' => ['nullable', 'string', 'max:150'],
        ]));

        return back()->with('success', "{$assignment->crew_name} sign off dari {$assignment->vessel}.");
    }

    private function query(array $filters)
    {
        return CrewAssignment::query()
            ->ofCompany()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['vessel'] ?? null, fn ($q, $vessel) => $q->where('vessel', $vessel))
            ->when($filters['rank'] ?? null, fn ($q, $rank) => $q->where('rank', $rank))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('crew_name', 'like', "%{$search}%")
                        ->orWhere('assignment_code', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            });
    }

    private function formOptions(): array
    {
        return [
            'candidates' => CrewCandidate::ofCompany()->orderBy('full_name')->get(['id', 'full_name', 'candidate_code', 'linked_employee_id', 'applied_rank']),
            'crews' => collect($this->erpnext->isConfigured()
                ? app(CrewRepositoryInterface::class)->paginate([], 200)->items()
                : [])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'vessels' => $this->options->vessels(),
            'ranks' => $this->options->ranks(),
            'statuses' => CrewAssignment::STATUSES,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'crew_candidate_id' => ['nullable', 'exists:crew_candidates,id'],
            'employee_id' => ['nullable', 'string', 'max:150'],
            'crew_name' => ['required', 'string', 'max:150'],
            'vessel' => ['nullable', 'string', 'max:150'],
            'rank' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(CrewAssignment::STATUSES)],
            'planned_sign_on_date' => ['nullable', 'date'],
            'sign_on_date' => ['nullable', 'date'],
            'sign_on_port' => ['nullable', 'string', 'max:100'],
            'contract_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'planned_sign_off_date' => ['nullable', 'date'],
            'sign_off_date' => ['nullable', 'date'],
            'sign_off_port' => ['nullable', 'string', 'max:100'],
            'sign_off_reason' => ['nullable', 'string', 'max:150'],
            'wage' => ['nullable', 'numeric', 'min:0'],
            'wage_currency' => ['nullable', 'string', 'max:5'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
