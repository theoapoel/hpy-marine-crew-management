<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrincipalRequest;
use App\Models\Principal;
use App\Services\Erpnext\ErpnextOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Principals — the owners and managers whose vessels we crew.
 *
 * Kept locally (they carry our own commercial terms) and mirrored into the ERP HPY
 * "Principal" doctype, so the fleet there can link to them.
 */
class PrincipalController extends Controller
{
    public function __construct(private readonly ErpnextOptions $options)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('principals.viewAny');

        $filters = $request->only('search', 'status', 'principal_type', 'country');

        $principals = Principal::query()
            ->ofCompany()
            ->withVesselCount()
            ->byType($filters['principal_type'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('country', $country))
            ->when(trim((string) ($filters['search'] ?? '')), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('principal_name', 'like', "%{$search}%")
                        ->orWhere('principal_code', 'like', "%{$search}%")
                        ->orWhere('legal_entity_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('principal_name')
            ->paginate(20)
            ->withQueryString();

        return view('principals.index', [
            'principals' => $principals,
            'filters' => $filters,
            'statuses' => Principal::STATUSES,
            'types' => Principal::TYPES,
            'countries' => Principal::ofCompany()->distinct()->orderBy('country')->pluck('country')->filter()->values(),
        ]);
    }

    public function create()
    {
        Gate::authorize('principals.create');

        return view('principals.create', $this->formOptions());
    }

    public function store(PrincipalRequest $request)
    {
        $principal = DB::transaction(function () use ($request) {
            $principal = Principal::create($this->payload($request));
            $this->saveContacts($principal, $request);

            return $principal;
        });

        $principal->syncToErp();

        return $request->input('after_save') === 'new'
            ? redirect()->route('principals.create')->with('success', "{$principal->principal_code} saved. Add the next one.")
            : redirect()->route('principals.show', $principal)->with('success', "{$principal->principal_code} saved.");
    }

    public function show(Principal $principal, Request $request)
    {
        Gate::authorize('principals.view');

        return view('principals.show', [
            'principal' => $principal->load('contactPersons'),
            'vessels' => $principal->erpVessels(),
            'tab' => $request->query('tab', 'overview'),
        ]);
    }

    public function edit(Principal $principal)
    {
        Gate::authorize('principals.update');

        return view('principals.edit', $this->formOptions() + ['principal' => $principal->load('contactPersons')]);
    }

    public function update(PrincipalRequest $request, Principal $principal)
    {
        DB::transaction(function () use ($request, $principal) {
            $principal->update($this->payload($request));
            $this->saveContacts($principal, $request);
        });

        $principal->syncToErp();

        return $request->input('after_save') === 'new'
            ? redirect()->route('principals.create')->with('success', "{$principal->principal_code} updated.")
            : redirect()->route('principals.show', $principal)->with('success', "{$principal->principal_code} updated.");
    }

    public function destroy(Principal $principal)
    {
        Gate::authorize('principals.delete');

        // A principal with ships still on our books would orphan those vessels.
        if (($fleet = count($principal->erpVessels())) > 0) {
            return back()->withErrors([
                'principal' => "{$principal->principal_name} masih punya {$fleet} kapal. Pindahkan atau lepas kapalnya dulu.",
            ]);
        }

        $principal->delete();

        return redirect()->route('principals.index')->with('success', "{$principal->principal_code} removed.");
    }

    /** Push this principal into ERP HPY on demand. */
    public function sync(Principal $principal)
    {
        Gate::authorize('principals.update');

        if ($principal->syncToErp()) {
            return back()->with('success', "{$principal->principal_code} synced to ERP HPY ({$principal->erpnext_name}).");
        }

        return back()->withErrors(['erpnext' => 'ERP HPY rejected this: ' . $principal->erpnext_sync_error]);
    }

    private function formOptions(): array
    {
        return [
            'types' => Principal::TYPES,
            'statuses' => Principal::STATUSES,
            'contractTypes' => Principal::CONTRACT_TYPES,
            'feeTypes' => Principal::FEE_TYPES,
            'countries' => $this->options->countries(),
            'addressTypes' => Principal::ADDRESS_TYPES,
            'currencies' => $this->options->currencies(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(PrincipalRequest $request): array
    {
        $data = collect($request->validated())->except('contact_persons')->all();

        $rows = fn (string $key, array $fields) => collect($data[$key] ?? [])
            ->map(fn ($row) => array_filter(array_intersect_key((array) $row, array_flip($fields)), 'filled'));

        // A row is kept when it says something beyond its type (or currency).
        $addresses = $rows('addresses', Principal::ADDRESS_FIELDS)
            ->filter(fn ($row) => Arr::except($row, ['address_type', 'country']) !== [])->values()->all();
        $fees = $rows('manning_fees', Principal::FEE_FIELDS)
            ->filter(fn ($row) => isset($row['fee_type']) || isset($row['amount']))->values()->all();

        // The first row of each stays on the single columns: search, the list and
        // ERP HPY's flat fields read those.
        $address = $addresses[0] ?? [];
        $fee = $fees[0] ?? [];

        return array_merge($data, [
            'addresses' => $addresses ?: null,
            'manning_fees' => $fees ?: null,
            'head_office_address' => $address['address'] ?? null,
            'city' => $address['city'] ?? null,
            'province' => $address['province'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'phone' => $address['phone'] ?? null,
            'fax' => $address['fax'] ?? null,
            'email' => $address['email'] ?? null,
            'wechat' => $address['wechat'] ?? null,
            'manning_fee_type' => $fee['fee_type'] ?? null,
            'manning_fee_amount' => $fee['amount'] ?? null,
            'currency' => $fee['currency'] ?? 'IDR',
        ]);
    }

    /** Replace the contact rows with what the form carried, dropping blank ones. */
    private function saveContacts(Principal $principal, PrincipalRequest $request): void
    {
        $rows = collect($request->validated()['contact_persons'] ?? [])
            ->filter(fn ($row) => filled($row['name'] ?? null))
            ->values();

        $principal->contactPersons()->delete();

        foreach ($rows as $index => $row) {
            $principal->contactPersons()->create([
                'name' => $row['name'],
                'position' => $row['position'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'whatsapp' => $row['whatsapp'] ?? null,
                'wechat' => $row['wechat'] ?? null,
                // Exactly one primary: the marked row, or the first one.
                'is_primary' => (bool) ($row['is_primary'] ?? false) || ($index === 0 && $rows->where('is_primary', '1')->isEmpty()),
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }
}
