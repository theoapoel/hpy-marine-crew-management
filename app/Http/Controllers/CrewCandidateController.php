<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrewCandidateRequest;
use App\Models\CrewCandidate;
use App\Services\Erpnext\ErpnextClient;
use App\Services\Erpnext\ErpnextOptions;
use App\Support\CocTypes;
use App\Support\ErpUser;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate Pool — the lifetime seafarer database.
 *
 * Candidates are local (SQLite) because they are not employees yet; only on promotion
 * does a record appear in ERP HPY. Rank and vessel-type dropdowns still come from
 * ERP HPY so the vocabulary stays the same across the app.
 */
class CrewCandidateController extends Controller
{
    /** Files the form can carry, mapped to the column that stores their path. */
    private const UPLOADS = [
        'photo' => 'photo_path',
        'cv' => 'cv_path',
        'id_scan' => 'id_scan_path',
        'seaman_book_scan' => 'seaman_book_scan_path',
        'coc_scan' => 'coc_scan_path',
    ];

    public function __construct(private readonly ErpnextOptions $options) {}

    public function index(Request $request)
    {
        Gate::authorize('candidates.viewAny');

        $filters = $request->only('search', 'status', 'source', 'applied_rank', 'coc_type', 'availability');
        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = CrewCandidate::query()
            ->ofCompany()
            ->byRank($filters['applied_rank'] ?? null)
            ->byCocType($filters['coc_type'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when(($filters['availability'] ?? null) === 'available', fn ($q) => $q->available())
            ->when(($filters['availability'] ?? null) === 'unavailable', fn ($q) => $q->whereNotIn('status', ['applicant', 'in_process', 'on_leave']))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('seaman_book_no', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('candidate_code', 'like', "%{$search}%");
                });
            });

        $sortable = ['full_name' => 'full_name', 'created_at' => 'created_at', 'availability_date' => 'availability_date'];

        return view('candidates.index', [
            'candidates' => $query->orderBy($sortable[$sort] ?? 'created_at', $direction)->paginate(20)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'ranks' => $this->options->ranks(),
            'statuses' => CrewCandidate::STATUSES,
            'sources' => CrewCandidate::SOURCES,
            'cocTypes' => app(CocTypes::class)->all(),
        ]);
    }

    public function create()
    {
        Gate::authorize('candidates.create');

        return view('candidates.create', $this->formOptions());
    }

    public function store(CrewCandidateRequest $request)
    {
        $candidate = CrewCandidate::create($this->payload($request));

        return $request->input('after_save') === 'new'
            ? redirect()->route('candidates.create')->with('success', "{$candidate->candidate_code} saved. Add the next candidate.")
            : redirect()->route('candidates.show', $candidate)->with('success', "{$candidate->candidate_code} saved.");
    }

    public function show(CrewCandidate $candidate)
    {
        Gate::authorize('candidates.view');

        return view('candidates.show', ['candidate' => $candidate]);
    }

    public function edit(CrewCandidate $candidate)
    {
        Gate::authorize('candidates.update');

        return view('candidates.edit', $this->formOptions() + ['candidate' => $candidate]);
    }

    public function update(CrewCandidateRequest $request, CrewCandidate $candidate)
    {
        $candidate->update($this->payload($request, $candidate));

        return $request->input('after_save') === 'new'
            ? redirect()->route('candidates.create')->with('success', "{$candidate->candidate_code} updated.")
            : redirect()->route('candidates.show', $candidate)->with('success', "{$candidate->candidate_code} updated.");
    }

    public function destroy(CrewCandidate $candidate)
    {
        Gate::authorize('candidates.delete');

        $candidate->delete();

        return redirect()->route('candidates.index')->with('success', "{$candidate->candidate_code} deleted.");
    }

    /** Bulk status change from the list's checkbox selection. */
    public function bulkStatus(Request $request)
    {
        Gate::authorize('candidates.update');

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'status' => ['required', 'in:'.implode(',', CrewCandidate::STATUSES)],
        ]);

        $changed = CrewCandidate::ofCompany()->whereIn('id', $data['ids'])->update([
            'status' => $data['status'],
            'updated_by' => ErpUser::id(),
        ]);

        return back()->with('success', "{$changed} candidates moved to status {$data['status']}.");
    }

    /** Export the current filter selection (or a checkbox selection) as CSV. */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('candidates.viewAny');

        $query = CrewCandidate::query()
            ->ofCompany()
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', (array) $request->input('ids')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('applied_rank'), fn ($q) => $q->where('applied_rank', $request->input('applied_rank')))
            ->orderBy('candidate_code');

        $columns = [
            'candidate_code', 'full_name', 'gender', 'date_of_birth', 'nik', 'seaman_book_no',
            'phone', 'email', 'applied_rank', 'coc_type', 'coc_number', 'coc_expiry',
            'years_of_experience', 'status', 'source', 'availability_date', 'expected_salary',
        ];

        return response()->streamDownload(function () use ($query, $columns) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $columns);

            $query->chunk(200, function ($rows) use ($handle, $columns) {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn ($c) => (string) $row->{$c}, $columns));
                }
            });

            fclose($handle);
        }, 'candidate-pool-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Duplicate check used by the form while typing: does this NIK or seaman book
     * already belong to someone in the pool?
     */
    public function duplicates(Request $request)
    {
        Gate::authorize('candidates.viewAny');

        $nik = trim((string) $request->query('nik'));
        $book = trim((string) $request->query('seaman_book_no'));
        $ignore = $request->query('ignore');

        if ($nik === '' && $book === '') {
            return response()->json(['matches' => []]);
        }

        $matches = CrewCandidate::query()
            ->ofCompany()
            ->when($ignore, fn ($q) => $q->where('uuid', '!=', $ignore))
            ->where(function ($q) use ($nik, $book) {
                if ($nik !== '') {
                    $q->orWhere('nik', $nik);
                }
                if ($book !== '') {
                    $q->orWhere('seaman_book_no', $book);
                }
            })
            ->limit(5)
            ->get(['uuid', 'candidate_code', 'full_name', 'nik', 'seaman_book_no', 'status']);

        return response()->json([
            'matches' => $matches->map(fn ($c) => [
                'code' => $c->candidate_code,
                'name' => $c->full_name,
                'field' => $c->nik === $nik && $nik !== '' ? 'NIK' : 'Seaman Book',
                'status' => $c->status,
                'url' => route('candidates.show', $c),
            ]),
        ]);
    }

    /** Hire the candidate: create the Employee in ERP HPY and link the records. */
    public function promote(CrewCandidate $candidate)
    {
        Gate::authorize('candidates.update');

        if ($candidate->linked_employee_id) {
            return back()->with('success', "This candidate is already Employee {$candidate->linked_employee_id}.");
        }

        try {
            $employee = $candidate->promoteToEmployee();
        } catch (RequestException $e) {
            report($e);

            return back()->withErrors(['erpnext' => 'ERP HPY rejected it: '.($e->response->json('exception') ?: $e->response->status())]);
        }

        return redirect()->route('crew.show', $employee->id)
            ->with('success', "{$candidate->candidate_code} promoted to Employee {$employee->id}.");
    }

    /**
     * Add a COC type from the candidate form: a record of the ERP HPY "COC Type"
     * master, offered in every COC dropdown straight after. Answers JSON for the form.
     */
    public function storeCocType(Request $request, CocTypes $types, ErpnextClient $erpnext): JsonResponse
    {
        Gate::authorize('candidates.create');

        $name = trim((string) $request->validate(['name' => ['required', 'string', 'max:140']])['name']);

        if (! $types->editable()) {
            return response()->json([
                'message' => 'COC types are still a fixed list in ERP HPY. Run php artisan erp:sync-candidate-doctype first.',
            ], 409);
        }

        try {
            if (! $erpnext->exists(CocTypes::DOCTYPE, $name)) {
                $erpnext->create(CocTypes::DOCTYPE, ['coc_type_name' => $name, 'is_active' => 1]);
            }
        } catch (RequestException $e) {
            report($e);

            return response()->json(['message' => 'ERP HPY rejected the type (status '.$e->response->status().').'], 422);
        }

        $this->options->forget(CocTypes::DOCTYPE);

        return response()->json(['name' => $name], 201);
    }

    private function formOptions(): array
    {
        return [
            'ranks' => $this->options->ranks(),
            'genders' => CrewCandidate::GENDERS,
            'maritalStatuses' => CrewCandidate::MARITAL_STATUSES,
            'cocTypes' => app(CocTypes::class)->all(),
            'cocTypesEditable' => app(CocTypes::class)->editable(),
            'sources' => CrewCandidate::SOURCES,
            'statuses' => CrewCandidate::STATUSES,
            'paidSources' => CrewCandidate::PAID_SOURCES,
        ];
    }

    /** Validated fields plus any uploaded files, ready for create/update. */
    private function payload(CrewCandidateRequest $request, ?CrewCandidate $candidate = null): array
    {
        $data = collect($request->validated())
            ->except(array_keys(self::UPLOADS) + ['other_documents'])
            ->all();

        // Only paid sources keep a cost; conditional fields are cleared otherwise.
        if (! in_array($data['source'] ?? null, CrewCandidate::PAID_SOURCES, true)) {
            $data['source_cost'] = null;
        }
        foreach (['referral' => 'referred_by_employee_id', 'agency' => 'source_agency_id', 'school' => 'source_school'] as $source => $field) {
            if (($data['source'] ?? null) !== $source) {
                $data[$field] = null;
            }
        }

        foreach (self::UPLOADS as $input => $column) {
            if ($request->file($input) instanceof UploadedFile) {
                $data[$column] = $request->file($input)->store('candidates', 'public');

                if ($candidate?->{$column}) {
                    Storage::disk('public')->delete($candidate->{$column});
                }
            }
        }

        if ($files = $request->file('other_documents')) {
            $stored = array_map(fn (UploadedFile $file) => [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('candidates', 'public'),
            ], $files);

            $data['other_documents'] = array_merge($candidate?->other_documents ?? [], $stored);
        }

        // Drop blank repeater rows before they reach the json column.
        if (isset($data['cop_certificates'])) {
            $data['cop_certificates'] = array_values(array_filter(
                $data['cop_certificates'],
                fn ($row) => filled($row['name'] ?? null),
            ));
        }

        return $data;
    }
}
