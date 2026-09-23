<?php

namespace App\Http\Controllers;

use App\Models\CrewApplication;
use App\Models\CrewAssignment;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    ) {}

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

    /**
     * Sign On desk: everyone waiting to board — and, below them, who boarded in the
     * last 30 days, so a crew signed on (here or from the assignment form) does not
     * simply vanish from the page that is meant to show sign ons.
     */
    public function signOnDesk(Request $request)
    {
        Gate::authorize('assignments.viewAny');

        return view('assignments.sign-on', [
            'assignments' => $this->query($request->only('search', 'vessel'))
                ->planned()
                ->orderBy('planned_sign_on_date')
                ->get(),
            'recent' => $this->query($request->only('search', 'vessel'))
                ->onboard()
                ->where('sign_on_date', '>=', now()->subDays(30)->toDateString())
                ->orderByDesc('sign_on_date')
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

        $assignment = CrewAssignment::create($this->prepared($this->validated($request)));

        return redirect()->route('assignments.show', $assignment)
            ->with('success', "{$assignment->assignment_code} created.");
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

        $assignment->update($this->prepared($this->validated($request), $assignment));

        return redirect()->route('assignments.show', $assignment)
            ->with('success', "{$assignment->assignment_code} updated.");
    }

    public function destroy(CrewAssignment $assignment)
    {
        Gate::authorize('assignments.delete');

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', "{$assignment->assignment_code} deleted.");
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

        return back()->with('success', "{$assignment->crew_name} signed on to {$assignment->vessel}.");
    }

    /**
     * Find the Employee, Candidate and Application of an assignment made without them,
     * then mirror it onto ERP HPY — the way to repair postings that never reached
     * Crew Master.
     */
    public function link(CrewAssignment $assignment)
    {
        Gate::authorize('assignments.update');

        $found = $this->linkData($assignment->only(['crew_name', 'employee_id', 'crew_candidate_id', 'crew_application_id']));
        $assignment->fill(array_filter($found, fn ($v) => filled($v)))->save();

        if (blank($assignment->employee_id)) {
            return back()->withErrors(['link' => "No single Employee in ERP HPY is named exactly \"{$assignment->crew_name}\". Pick the Employee in the edit form."]);
        }

        // Saving only syncs when a watched field changed; a repair must sync regardless.
        $assignment->syncToErp();

        return back()->with('success', "{$assignment->assignment_code} linked to {$assignment->employee_id}.");
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

        return back()->with('success', "{$assignment->crew_name} signed off from {$assignment->vessel}.");
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
            'crews' => $this->employees(),
            'vessels' => $this->options->vessels(),
            'ranks' => $this->options->ranks(),
            'statuses' => CrewAssignment::STATUSES,
            'currencies' => rescue(fn () => $this->options->currencies(), [], report: true) ?: ['IDR', 'USD'],
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

    /**
     * Employees of the session company for the Employee dropdown: id, name and rank,
     * so picking one can fill the rest of the form.
     *
     * @return array<int, array{id: string, name: string, rank: ?string}>
     */
    private function employees(): array
    {
        if (! $this->erpnext->isConfigured()) {
            return [];
        }

        $filters = ($company = $this->erpnext->company()) ? [['company', '=', $company]] : [];

        return rescue(fn () => collect($this->erpnext->list(
            (string) config('services.erpnext.crew_doctype', 'Employee'),
            ['name', 'employee_name', 'custom_rank'],
            $filters,
            2000,
        ))->map(fn ($row) => [
            'id' => (string) $row['name'],
            'name' => (string) ($row['employee_name'] ?? $row['name']),
            'rank' => $row['custom_rank'] ?? null,
        ])->sortBy('name')->values()->all(), [], report: true);
    }

    /**
     * What the form sends, completed the way the desks would complete it:
     *
     * - the links nobody filled in are found (see link());
     * - a contract length gives the planned sign off, counted from the sign on (or
     *   the planned sign on) — unless a date was typed in;
     * - moving a planned crew to Onboard here is a sign on, so it gets a sign on date.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepared(array $data, ?CrewAssignment $assignment = null): array
    {
        // Keep an application already linked (a hire sets it); the form does not carry it.
        $data['crew_application_id'] = $assignment?->crew_application_id;
        $data = $this->linkData($data);

        if (($data['status'] ?? null) === 'onboard' && blank($data['sign_on_date'] ?? null)) {
            $data['sign_on_date'] = ($data['planned_sign_on_date'] ?? null) ?: now()->toDateString();
        }

        $start = ($data['sign_on_date'] ?? null) ?: ($data['planned_sign_on_date'] ?? null);
        if (filled($data['contract_months'] ?? null) && $start && blank($data['planned_sign_off_date'] ?? null)) {
            $data['planned_sign_off_date'] = CrewAssignment::contractEnd($start, (int) $data['contract_months']);
        }

        return $data;
    }

    /**
     * Fill in the Employee, Candidate and Application an assignment belongs to when
     * the form left them empty. Without the Employee nothing reaches ERP HPY — Crew
     * Master would never hear of the posting — so it is looked for hardest:
     *
     * 1. the Employee the chosen candidate was promoted to;
     * 2. the one Employee of this company whose name matches the crew name exactly;
     * then the candidate behind that Employee (or with that exact name), and that
     * candidate's application — the hired one, else the latest.
     *
     * A name matching several people is left alone rather than guessed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function linkData(array $data): array
    {
        $name = trim((string) ($data['crew_name'] ?? ''));
        $candidate = filled($data['crew_candidate_id'] ?? null) ? CrewCandidate::find($data['crew_candidate_id']) : null;

        if (blank($data['employee_id'] ?? null)) {
            $data['employee_id'] = $candidate?->linked_employee_id
                ?: $this->onlyOne(collect($this->employees())->filter(fn ($e) => strcasecmp($e['name'], $name) === 0)->pluck('id'));
        }

        if (! $candidate) {
            $candidate = filled($data['employee_id'] ?? null)
                ? CrewCandidate::ofCompany()->where('linked_employee_id', $data['employee_id'])->first()
                : null;
            $candidate ??= $name !== ''
                ? $this->onlyOne(CrewCandidate::ofCompany()->where('full_name', $name)->get())
                : null;
            $data['crew_candidate_id'] = $candidate?->id;
        }

        if ($candidate && blank($data['crew_application_id'] ?? null)) {
            $data['crew_application_id'] = CrewApplication::where('crew_candidate_id', $candidate->id)
                ->orderByRaw("case when stage = 'hired' then 0 else 1 end")
                ->latest('id')
                ->value('id');
        }

        return $data;
    }

    /** The single item of a collection, or null when there are none or several. */
    private function onlyOne(Collection $items): mixed
    {
        return $items->count() === 1 ? $items->first() : null;
    }
}
