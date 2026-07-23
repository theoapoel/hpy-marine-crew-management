<?php

namespace App\Http\Controllers;

use App\Models\CrewApplication;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Recruitment Pipeline — every open application, shown as a board of stages.
 * Hiring an application promotes the candidate into ERP HPY and opens an assignment.
 */
class CrewApplicationController extends Controller
{
    public function __construct(private readonly ErpnextOptions $options)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('applications.viewAny');

        $filters = $request->only('search', 'stage', 'applied_rank', 'vessel');

        $applications = CrewApplication::with('candidate')
            ->ofCompany()
            ->inStage($filters['stage'] ?? null)
            ->when($filters['applied_rank'] ?? null, fn ($q, $rank) => $q->where('applied_rank', $rank))
            ->when($filters['vessel'] ?? null, fn ($q, $vessel) => $q->where('vessel', $vessel))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('application_code', 'like', "%{$search}%")
                        ->orWhereHas('candidate', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->latest('applied_date')
            ->get();

        return view('applications.index', [
            'view' => $request->query('view') === 'list' ? 'list' : 'board',
            'applications' => $applications,
            'byStage' => $applications->groupBy('stage'),
            'filters' => $filters,
            'stages' => CrewApplication::STAGES,
            'closedStages' => CrewApplication::CLOSED_STAGES,
            'ranks' => $this->options->ranks(),
            'vessels' => $this->options->vessels(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('applications.create');

        return view('applications.create', $this->formOptions() + [
            'preselected' => $request->query('candidate'),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('applications.create');

        $application = CrewApplication::create($this->validated($request));

        return redirect()->route('applications.show', $application)
            ->with('success', "{$application->application_code} dibuat.");
    }

    public function show(CrewApplication $application)
    {
        Gate::authorize('applications.view');

        return view('applications.show', [
            'application' => $application->load('candidate', 'assignment'),
            'stages' => CrewApplication::STAGES,
        ]);
    }

    public function edit(CrewApplication $application)
    {
        Gate::authorize('applications.update');

        return view('applications.edit', $this->formOptions() + ['application' => $application]);
    }

    public function update(Request $request, CrewApplication $application)
    {
        Gate::authorize('applications.update');

        $application->update($this->validated($request, $application));

        return redirect()->route('applications.show', $application)
            ->with('success', "{$application->application_code} diperbarui.");
    }

    public function destroy(CrewApplication $application)
    {
        Gate::authorize('applications.delete');

        $application->delete();

        return redirect()->route('applications.index')->with('success', "{$application->application_code} dihapus.");
    }

    /** Move an application one stage forward (or straight to a chosen stage). */
    public function advance(Request $request, CrewApplication $application)
    {
        Gate::authorize('applications.update');

        $data = $request->validate([
            'stage' => ['nullable', Rule::in(CrewApplication::STAGES)],
            'interview_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'interview_notes' => ['nullable', 'string', 'max:2000'],
            'mcu_result' => ['nullable', Rule::in(CrewApplication::MCU_RESULTS)],
            'offered_salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $application->advance($data);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            return back()->withErrors(['erpnext' => 'ERP HPY menolak saat membuat Employee: ' . ($e->response->json('exception') ?: $e->response->status())]);
        }

        return back()->with('success', "{$application->application_code} → tahap {$application->stage}.");
    }

    /** Close an application: rejected or withdrawn. */
    public function close(Request $request, CrewApplication $application)
    {
        Gate::authorize('applications.update');

        $data = $request->validate([
            'stage' => ['required', Rule::in(CrewApplication::CLOSED_STAGES)],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $application->close($data['stage'], $data['rejection_reason'] ?? null);

        return back()->with('success', "{$application->application_code} ditutup ({$data['stage']}).");
    }

    private function formOptions(): array
    {
        return [
            'candidates' => CrewCandidate::ofCompany()->orderBy('full_name')->get(['id', 'full_name', 'candidate_code', 'applied_rank']),
            'ranks' => $this->options->ranks(),
            'vessels' => $this->options->vessels(),
            'stages' => array_merge(CrewApplication::STAGES, CrewApplication::CLOSED_STAGES),
            'mcuResults' => CrewApplication::MCU_RESULTS,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CrewApplication $application = null): array
    {
        return $request->validate([
            'crew_candidate_id' => ['required', 'exists:crew_candidates,id'],
            'applied_rank' => ['nullable', 'string', 'max:100'],
            'vessel' => ['nullable', 'string', 'max:150'],
            'principal' => ['nullable', 'string', 'max:150'],
            'stage' => ['required', Rule::in(array_merge(CrewApplication::STAGES, CrewApplication::CLOSED_STAGES))],
            'applied_date' => ['required', 'date'],
            'screening_date' => ['nullable', 'date'],
            'interview_date' => ['nullable', 'date'],
            'interview_score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'interview_notes' => ['nullable', 'string', 'max:2000'],
            'mcu_date' => ['nullable', 'date'],
            'mcu_result' => ['nullable', Rule::in(CrewApplication::MCU_RESULTS)],
            'offer_date' => ['nullable', 'date'],
            'offered_salary' => ['nullable', 'numeric', 'min:0'],
            'decision_date' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
