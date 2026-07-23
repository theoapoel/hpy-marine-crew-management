<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncCrewFields;
use App\Repositories\CrewRepositoryInterface;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    private const STATUSES = ['Onboard', 'Standby', 'Sign Off'];

    public function __construct(
        private readonly CrewRepositoryInterface $crews,
        private readonly ErpnextOptions $options,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only('search', 'status');

        return view('crew.index', [
            'crews' => $this->crews->paginate($filters, 15),
            'statuses' => self::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('crew.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return $this->guarded(function () use ($data) {
            $this->crews->create($data);

            return redirect()->route('crew.index')->with('success', 'Crew member added.');
        });
    }

    public function show(int|string $crew)
    {
        return view('crew.show', ['crew' => $this->crews->find($crew)]);
    }

    public function edit(int|string $crew)
    {
        return view('crew.edit', $this->formOptions() + ['crew' => $this->crews->find($crew)]);
    }

    public function update(Request $request, int|string $crew)
    {
        $data = $this->validated($request);

        return $this->guarded(function () use ($crew, $data) {
            $this->crews->update($crew, $data);

            return redirect()->route('crew.index')->with('success', 'Crew member updated.');
        });
    }

    public function destroy(int|string $crew)
    {
        $this->crews->delete($crew);

        return redirect()->route('crew.index')->with('success', 'Crew member removed.');
    }

    /**
     * ERP HPY runs its own validation (mandatory fields, link targets that must exist).
     * Show that back on the form instead of a 500 page.
     */
    private function guarded(\Closure $write)
    {
        try {
            return $write();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'erpnext' => 'ERP HPY menolak data ini: ' . $this->erpMessage($e),
            ]);
        }
    }

    /** Frappe wraps the useful part in a json body; dig it out. */
    private function erpMessage(\Illuminate\Http\Client\RequestException $e): string
    {
        $exception = (string) ($e->response->json('exception') ?: '');

        if ($exception !== '') {
            return trim(preg_replace('/^[\w.]+Error:\s*/', '', $exception));
        }

        $messages = $e->response->json('_server_messages');

        if (is_string($messages) && ($decoded = json_decode($messages, true))) {
            return collect($decoded)
                ->map(fn ($m) => is_string($m) ? (json_decode($m, true)['message'] ?? $m) : $m)
                ->implode(' ');
        }

        return 'status ' . $e->response->status();
    }

    /** Dropdown values, all of them straight from ERP HPY. */
    private function formOptions(): array
    {
        return [
            'statuses' => self::STATUSES,
            'ranks' => $this->options->ranks(),
            'salutations' => $this->options->salutations(),
            'vessels' => $this->options->vessels(),
            'genders' => $this->options->genders(),
            'departments' => $this->options->departments(),
            'branches' => $this->options->branches(),
            'certificateTypes' => SyncCrewFields::CERTIFICATE_TYPES,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            // Overview — mirrors the mandatory fields on the Employee doctype.
            'salutation' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'date_of_joining' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],

            // Assignment
            'rank' => ['required', 'string', 'max:140'],
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'vessel' => ['nullable', 'string', 'max:140'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:140'],
            'branch' => ['nullable', 'string', 'max:140'],
            'sign_on_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:sign_on_date'],

            // Seafarer details
            'nationality' => ['nullable', 'string', 'max:100'],
            'seaman_book_no' => ['nullable', 'string', 'max:100'],
            'seaman_book_expiry' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'health_details' => ['nullable', 'string', 'max:500'],

            // Passport
            'passport_number' => ['nullable', 'string', 'max:100'],
            'date_of_issue' => ['nullable', 'date'],
            'valid_upto' => ['nullable', 'date'],
            'place_of_issue' => ['nullable', 'string', 'max:140'],

            // Address & contacts
            'cell_number' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:140'],
            'company_email' => ['nullable', 'email', 'max:140'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'person_to_be_contacted' => ['nullable', 'string', 'max:140'],
            'emergency_phone_number' => ['nullable', 'string', 'max:50'],
            'relation' => ['nullable', 'string', 'max:100'],

            // Bank
            'bank_name' => ['nullable', 'string', 'max:140'],
            'bank_ac_no' => ['nullable', 'string', 'max:100'],

            // Certificates (child table)
            'certificates' => ['nullable', 'array'],
            'certificates.*.certificate_type' => ['nullable', 'string', 'max:140'],
            'certificates.*.certificate_number' => ['nullable', 'string', 'max:140'],
            'certificates.*.issued_by' => ['nullable', 'string', 'max:140'],
            'certificates.*.issue_date' => ['nullable', 'date'],
            'certificates.*.expiry_date' => ['nullable', 'date'],
            'certificates.*.remarks' => ['nullable', 'string', 'max:500'],
            'certificates.*.attachment' => ['nullable', 'string', 'max:500'],
            'certificates.*.file' => ['nullable', 'file', 'max:10240'],
        ]);

        // Keep the display name the rest of the app uses in sync with the parts.
        $data['name'] = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ])));

        return $data;
    }
}
