<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\Principal;
use App\Services\Erpnext\ErpnextClient;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * Fleet, kept in the instance's own "Vessel" Doctype — identification, class,
 * dimensions, tonnage — plus its certificate table and the crew currently aboard
 * (from Crew Assignment).
 *
 * Vessels belong to a company, and a session only ever sees, edits or creates within
 * the company picked in the header.
 */
class VesselController extends Controller
{
    private const LIST_FIELDS = [
        'name', 'vessel_name', 'company', 'principal', 'vessel_photo', 'vessel_type', 'imo_number',
        'call_sign', 'flag_state', 'year_built', 'gross_tonnage', 'deadweight',
        'classification_society',
    ];

    /** Plain fields the form writes straight through to ERP HPY. */
    private const FIELDS = [
        // Identification
        'vessel_name', 'principal', 'imo_number', 'mmsi_number', 'call_sign', 'official_number', 'ex_name',
        // Registration & class
        'flag_state', 'port_of_registry', 'date_of_registration',
        'classification_society', 'class_number', 'class_notation',
        // Specification
        'vessel_type', 'sub_type', 'year_built', 'builder', 'country_of_build',
        'hull_number', 'keel_laid_date', 'delivery_date',
        // Dimensions
        'length_overall', 'length_bp', 'breadth', 'depth_moulded',
        'draft_summer', 'draft_ballast', 'freeboard',
        // Tonnage & capacity
        'gross_tonnage', 'net_tonnage', 'deadweight', 'displacement',
        'cargo_capacity', 'fuel_capacity', 'fresh_water_capacity', 'ballast_capacity',
        // Propulsion
        'service_speed', 'max_speed',
    ];

    public function __construct(
        private readonly ErpnextClient $erpnext,
        private readonly ErpnextOptions $options,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('vessels.viewAny');

        $filters = $request->only('search', 'vessel_type');

        $erpFilters = [];

        // Only the fleet of the company the session picked.
        if ($company = $this->erpnext->company()) {
            $erpFilters[] = ['company', '=', $company];
        }
        if ($type = $filters['vessel_type'] ?? null) {
            $erpFilters[] = ['vessel_type', '=', $type];
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $erpFilters[] = ['vessel_name', 'like', "%{$search}%"];
        }

        $vessels = collect($this->erpnext->list('Vessel', self::LIST_FIELDS, $erpFilters, 200));

        // How many crew each vessel currently carries, from our own assignments.
        $onboard = CrewAssignment::ofCompany()->onboard()
            ->selectRaw('vessel, count(*) as total')
            ->groupBy('vessel')
            ->pluck('total', 'vessel');

        $planned = CrewAssignment::ofCompany()->planned()
            ->selectRaw('vessel, count(*) as total')
            ->groupBy('vessel')
            ->pluck('total', 'vessel');

        // ERP HPY leaves empty fields out of list rows; give the view every key.
        $blank = array_fill_keys(self::LIST_FIELDS, null);

        return view('vessels.index', [
            'vessels' => $vessels->map(fn ($v) => (object) ($v + $blank + [
                'onboard' => $onboard[$v['name']] ?? 0,
                'planned' => $planned[$v['name']] ?? 0,
            ])),
            'filters' => $filters,
            'types' => $this->options->fieldOptions('Vessel', 'vessel_type'),
        ]);
    }

    public function create()
    {
        Gate::authorize('vessels.create');

        return view('vessels.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        Gate::authorize('vessels.create');

        $data = $this->validated($request);

        return $this->guarded(function () use ($data, $request) {
            $vessel = $this->erpnext->create('Vessel', $this->payload($data));
            $this->saveFiles($vessel['name'], $request);
            $this->saveCertificates($vessel['name'], $data, $request);

            return redirect()->route('vessels.show', $vessel['name'])
                ->with('success', "{$vessel['name']} saved.");
        });
    }

    public function show(string $vessel)
    {
        Gate::authorize('vessels.view');

        $document = $this->vesselOfCompany($vessel);

        return view('vessels.show', [
            'vessel' => (object) $document,
            'certificates' => collect($document['certificates_table'] ?? [])->map(fn ($c) => (object) $c),
            'engines' => collect($document['engines_table'] ?? [])->map(fn ($e) => (object) $e),
            'manning' => collect($document['manning_table'] ?? [])->map(fn ($m) => (object) $m),
            'crew' => CrewAssignment::ofCompany()->onboard()->where('vessel', $vessel)->orderBy('rank')->get(),
            // Booked on this ship but not aboard yet — otherwise a fresh assignment
            // looks like it never happened.
            'planned' => CrewAssignment::ofCompany()->planned()->where('vessel', $vessel)
                ->orderBy('planned_sign_on_date')
                ->get(),
            'history' => CrewAssignment::ofCompany()->where('vessel', $vessel)
                ->where('status', 'signed_off')
                ->latest('sign_off_date')
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(string $vessel)
    {
        Gate::authorize('vessels.update');

        $document = $this->vesselOfCompany($vessel);

        return view('vessels.edit', $this->formOptions() + [
            'vessel' => (object) $document,
            'certificates' => $document['certificates_table'] ?? [],
        ]);
    }

    public function update(Request $request, string $vessel)
    {
        Gate::authorize('vessels.update');

        $this->vesselOfCompany($vessel); // refuse another company's ship
        $data = $this->validated($request);

        return $this->guarded(function () use ($vessel, $data, $request) {
            $this->erpnext->update('Vessel', $vessel, $this->payload($data));
            $this->saveFiles($vessel, $request);
            $this->saveCertificates($vessel, $data, $request);

            return redirect()->route('vessels.show', $vessel)->with('success', "{$vessel} updated.");
        });
    }

    public function destroy(string $vessel)
    {
        Gate::authorize('vessels.delete');

        $this->vesselOfCompany($vessel);

        return $this->guarded(function () use ($vessel) {
            $this->erpnext->delete('Vessel', $vessel);

            return redirect()->route('vessels.index')->with('success', "{$vessel} removed.");
        });
    }

    /** Fetch a vessel, refusing anything outside the session company. */
    private function vesselOfCompany(string $vessel): array
    {
        $document = $this->erpnext->get('Vessel', $vessel);
        $company = $this->erpnext->company();

        abort_if($company && ($document['company'] ?? null) !== $company, 404);

        return $document;
    }

    private function formOptions(): array
    {
        return [
            'types' => $this->options->fieldOptions('Vessel', 'vessel_type'),
            'societies' => $this->options->selectOptions('Vessel', 'classification_society'),
            'countries' => $this->options->countries(),
            'certificateTypes' => $this->options->fieldOptions('Vessel Certificate', 'certificate_type'),
            'certificateStatuses' => $this->options->selectOptions('Vessel Certificate', 'status'),
            'company' => $this->erpnext->company(),
            'principals' => Principal::ofCompany()
                ->whereNotNull('erpnext_name')
                ->orderBy('principal_name')
                ->pluck('principal_name', 'erpnext_name'),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $numbers = [
            'length_overall', 'length_bp', 'breadth', 'depth_moulded', 'draft_summer',
            'draft_ballast', 'freeboard', 'gross_tonnage', 'net_tonnage', 'deadweight',
            'displacement', 'cargo_capacity', 'fuel_capacity', 'fresh_water_capacity',
            'ballast_capacity', 'service_speed', 'max_speed',
        ];

        $rules = [
            'vessel_name' => ['required', 'string', 'max:150'],
            'principal' => ['nullable', 'string', 'max:150'],
            'vessel_type' => ['required', 'string', 'max:100'],
            'imo_number' => ['nullable', 'string', 'max:20'],
            'mmsi_number' => ['nullable', 'string', 'max:20'],
            'call_sign' => ['nullable', 'string', 'max:20'],
            'official_number' => ['nullable', 'string', 'max:50'],
            'ex_name' => ['nullable', 'string', 'max:150'],
            'flag_state' => ['nullable', 'string', 'max:100'],
            'port_of_registry' => ['nullable', 'string', 'max:100'],
            'date_of_registration' => ['nullable', 'date'],
            'classification_society' => ['nullable', 'string', 'max:100'],
            'class_number' => ['nullable', 'string', 'max:50'],
            'class_notation' => ['nullable', 'string', 'max:100'],
            'sub_type' => ['nullable', 'string', 'max:100'],
            'year_built' => ['nullable', 'integer', 'min:1900', 'max:' . (now()->year + 5)],
            'builder' => ['nullable', 'string', 'max:150'],
            'country_of_build' => ['nullable', 'string', 'max:100'],
            'hull_number' => ['nullable', 'string', 'max:50'],
            'keel_laid_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'ga_drawing_file' => ['nullable', 'file', 'max:10240'],
            'certificates' => ['nullable', 'array'],
            'certificates.*.certificate_type' => ['nullable', 'string', 'max:100'],
            'certificates.*.certificate_name' => ['nullable', 'string', 'max:150'],
            'certificates.*.certificate_number' => ['nullable', 'string', 'max:100'],
            'certificates.*.issued_by' => ['nullable', 'string', 'max:150'],
            'certificates.*.issue_date' => ['nullable', 'date'],
            'certificates.*.expiry_date' => ['nullable', 'date'],
            'certificates.*.status' => ['nullable', 'string', 'max:50'],
            'certificates.*.attachment' => ['nullable', 'string', 'max:500'],
            'certificates.*.file' => ['nullable', 'file', 'max:10240'],
            'certificates.*.remarks' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($numbers as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0'];
        }

        return $request->validate($rules);
    }

    /**
     * Straight fields only; the company always comes from the session so a vessel
     * cannot be filed under someone else's fleet.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $payload = ['company' => $this->erpnext->company()];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }

    /**
     * The vessel photo and GA drawing, uploaded only when a new file was picked so an
     * edit that leaves them alone keeps what is already there.
     */
    private function saveFiles(string $vessel, Request $request): void
    {
        $patch = [];

        foreach (['photo' => 'vessel_photo', 'ga_drawing_file' => 'ga_drawing'] as $input => $field) {
            $file = $request->file($input);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $patch[$field] = $this->erpnext->upload(
                (string) $file->get(),
                $file->getClientOriginalName(),
                'Vessel',
                $vessel,
            );
        }

        if ($patch !== []) {
            $this->erpnext->update('Vessel', $vessel, $patch);
        }
    }

    /**
     * Certificate rows, uploading any newly picked scan. Skipped entirely when the
     * form carried no certificate section.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveCertificates(string $vessel, array $data, Request $request): void
    {
        if (! array_key_exists('certificates', $data)) {
            return;
        }

        $rows = [];

        foreach ((array) $data['certificates'] as $index => $certificate) {
            if (blank($certificate['certificate_type'] ?? null)) {
                continue; // blank repeater row
            }

            $file = $request->file("certificates.{$index}.file");
            $attachment = $certificate['attachment'] ?? null;

            if ($file instanceof UploadedFile) {
                $attachment = $this->erpnext->upload(
                    (string) $file->get(),
                    $file->getClientOriginalName(),
                    'Vessel',
                    $vessel,
                );
            }

            $rows[] = array_filter([
                'certificate_type' => $certificate['certificate_type'],
                'certificate_name' => $certificate['certificate_name'] ?? null,
                'certificate_number' => $certificate['certificate_number'] ?? null,
                'issued_by' => $certificate['issued_by'] ?? null,
                'issue_date' => $certificate['issue_date'] ?? null,
                'expiry_date' => $certificate['expiry_date'] ?? null,
                'status' => ($certificate['status'] ?? null) ?: $this->certificateStatus($certificate['expiry_date'] ?? null),
                'attachment' => $attachment,
                'remarks' => $certificate['remarks'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $this->erpnext->update('Vessel', $vessel, ['certificates_table' => $rows]);
    }

    /** Same reading as the crew documents: valid / expiring within 60 days / expired. */
    private function certificateStatus(?string $expiry): string
    {
        if (! $expiry) {
            return 'Valid';
        }

        $date = \Illuminate\Support\Carbon::parse($expiry);

        return match (true) {
            $date->isPast() => 'Expired',
            $date->lessThanOrEqualTo(now()->addDays(60)) => 'Expiring Soon',
            default => 'Valid',
        };
    }

    /** ERP HPY runs its own validation; show it on the form instead of a 500. */
    private function guarded(\Closure $write)
    {
        try {
            return $write();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            $message = (string) ($e->response->json('exception') ?: 'status ' . $e->response->status());

            throw \Illuminate\Validation\ValidationException::withMessages([
                'erpnext' => 'ERP HPY rejected this: ' . trim(preg_replace('/^[\w.]+Error:\s*/', '', $message)),
            ]);
        }
    }
}
