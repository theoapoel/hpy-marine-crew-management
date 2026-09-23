<?php

namespace App\Http\Controllers;

use App\Repositories\CrewRepositoryInterface;
use App\Services\Erpnext\ErpnextOptions;
use App\Support\CrewDocumentTypes;
use App\Support\Departments;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CrewController extends Controller
{
    private const STATUSES = ['Onboard', 'Standby', 'Sign Off'];

    public function __construct(
        private readonly CrewRepositoryInterface $crews,
        private readonly ErpnextOptions $options,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only('search', 'status', 'rank');

        return view('crew.index', [
            'crews' => $this->crews->paginate($filters, 15),
            'statuses' => self::STATUSES,
            'filters' => $filters,
            'rankCards' => $this->rankCards(),
        ]);
    }

    /**
     * One card per rank for the top of Crew Master: grouped by department (Deck,
     * Engine, Catering) and biggest first within each. A failed summary only costs
     * the cards, never the list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rankCards(): array
    {
        try {
            $summary = $this->crews->rankSummary();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $order = array_flip(array_keys(Departments::COLORS));

        return collect($summary)
            ->map(function (array $counts, string $rank) {
                $department = $rank === CrewRepositoryInterface::RANK_NONE ? 'Other' : Departments::of($rank);

                return [
                    'rank' => $rank,
                    'department' => $department,
                    'color' => Departments::color($department),
                    'total' => $counts['total'] ?? 0,
                    'onboard' => $counts['Onboard'] ?? 0,
                    'standby' => $counts['Standby'] ?? 0,
                    'signed_off' => $counts['Sign Off'] ?? 0,
                ];
            })
            ->sortBy([
                fn ($a, $b) => $order[$a['department']] <=> $order[$b['department']],
                fn ($a, $b) => $b['total'] <=> $a['total'],
                fn ($a, $b) => strcmp($a['rank'], $b['rank']),
            ])
            ->values()
            ->all();
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
        } catch (RequestException $e) {
            report($e);

            throw ValidationException::withMessages([
                'erpnext' => 'ERP HPY menolak data ini: '.$this->erpMessage($e),
            ]);
        }
    }

    /** Frappe wraps the useful part in a json body; dig it out. */
    private function erpMessage(RequestException $e): string
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

        return 'status '.$e->response->status();
    }

    /**
     * Add a certificate type from the crew form, so a new kind of document does not
     * have to wait for someone with ERP HPY desk access. Answers JSON for the form.
     */
    public function storeCertificateType(Request $request, CrewDocumentTypes $types): JsonResponse
    {
        $name = trim((string) $request->validate([
            'name' => ['required', 'string', 'max:140'],
        ])['name']);

        if (! $types->editable()) {
            return response()->json([
                'message' => 'Document types are still a fixed list in ERP HPY. Run php artisan erp:sync-crew-fields first.',
            ], 409);
        }

        try {
            $types->create($name);
        } catch (RequestException $e) {
            return response()->json(['message' => 'ERP HPY rejected the type: '.$this->erpMessage($e)], 422);
        }

        return response()->json(['name' => $name], 201);
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
            'currencies' => $this->options->currencies(),
            'certificateTypes' => $this->certificateTypes(),
            // Only a Link can take new types; until the sync has run it is a fixed Select.
            'certificateTypesEditable' => app(CrewDocumentTypes::class)->editable(),
        ];
    }

    /** @return array<int, string> */
    private function certificateTypes(): array
    {
        return app(CrewDocumentTypes::class)->names();
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
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'vessel' => ['nullable', 'string', 'max:140'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:140'],
            'sign_on_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:sign_on_date'],

            // Seafarer details & contacts
            'nationality' => ['nullable', 'string', 'max:100'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'health_details' => ['nullable', 'string', 'max:500'],
            'cell_number' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:140'],
            'company_email' => ['nullable', 'email', 'max:140'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'person_to_be_contacted' => ['nullable', 'string', 'max:140'],
            'emergency_phone_number' => ['nullable', 'string', 'max:50'],
            'relation' => ['nullable', 'string', 'max:100'],

            // Bank (child table) and last salary
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank_name' => ['nullable', 'required_with:bank_accounts.*.account_number', 'string', 'max:140'],
            'bank_accounts.*.account_holder' => ['nullable', 'string', 'max:140'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.bank_address' => ['nullable', 'string', 'max:500'],
            'last_salary' => ['nullable', 'numeric', 'min:0'],
            'last_salary_currency' => ['nullable', 'required_with:last_salary', 'string', 'max:10'],

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

        $data += $this->identityFields($data['certificates'] ?? []);

        // Keep the display name the rest of the app uses in sync with the parts.
        $data['name'] = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ])));

        return $data;
    }

    /**
     * Passport and seaman book are edited as certificate rows, but ERP HPY and the
     * rest of the app still read the Employee fields — so the rows are copied onto
     * them. A row that was removed clears its fields.
     *
     * @param  array<int, array<string, mixed>>  $certificates
     * @return array<string, string>
     */
    private function identityFields(array $certificates): array
    {
        $row = fn (string $type) => collect($certificates)->first(fn ($c) => ($c['certificate_type'] ?? null) === $type) ?? [];
        $passport = $row('Passport');
        $seamanBook = $row('Seaman Book');

        return [
            'passport_number' => (string) ($passport['certificate_number'] ?? ''),
            'date_of_issue' => (string) ($passport['issue_date'] ?? ''),
            'valid_upto' => (string) ($passport['expiry_date'] ?? ''),
            'place_of_issue' => (string) ($passport['issued_by'] ?? ''),
            'seaman_book_no' => (string) ($seamanBook['certificate_number'] ?? ''),
            'seaman_book_expiry' => (string) ($seamanBook['expiry_date'] ?? ''),
        ];
    }
}
