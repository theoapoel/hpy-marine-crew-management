@extends('layouts.app')

@section('title', $principal->principal_name . ' · HPYMarine')
@section('heading', 'Principals')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $fmt = fn ($d) => $d ? $d->format('d M Y') : '—';
    $statusColors = [
      'prospect' => 'bg-slate-100 text-slate-600 border-slate-200',
      'active' => 'bg-green-50 text-green-700 border-green-200',
      'on_hold' => 'bg-amber-50 text-amber-700 border-amber-200',
      'terminated' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
    $tabs = ['overview' => 'Overview', 'vessels' => 'Vessels (' . count($vessels) . ')', 'contacts' => 'Contact Persons', 'notes' => 'Notes'];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <a href="{{ route('principals.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Principals</a>
      <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $principal->principal_name }}</h1>
      <p class="text-sm text-muted mt-1">
        <span class="font-mono">{{ $principal->principal_code }}</span> ·
        {{ $text($principal->principal_type) }} · {{ $principal->country }}
      </p>
      <div class="mt-2 flex items-center gap-2">
        <span class="chip border {{ $statusColors[$principal->status] ?? '' }}">{{ $text($principal->status) }}</span>
        @if($principal->is_contract_active)
          <span class="chip border bg-green-50 text-green-700 border-green-200">Contract Active</span>
        @endif
        <x-erpnext-sync-badge :model="$principal" />
      </div>
    </div>

    <div class="flex items-center gap-2">
      @can('principals.update')
        <a href="{{ route('principals.edit', $principal) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
        <form method="POST" action="{{ route('principals.sync', $principal) }}">
          @csrf
          <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Sync to ERP HPY</button>
        </form>
      @endcan
      @can('principals.delete')
        <form method="POST" action="{{ route('principals.destroy', $principal) }}"
              onsubmit="return confirm('Delete {{ $principal->principal_name }}?')">
          @csrf @method('DELETE')
          <button class="text-sm text-rose-600 hover:text-rose-700 border border-line rounded-md px-4 py-2">Delete</button>
        </form>
      @endcan
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  {{-- Tabs --}}
  <div class="flex items-center gap-1 border-b border-line">
    @foreach($tabs as $key => $heading)
      <a href="{{ route('principals.show', [$principal, 'tab' => $key]) }}"
         class="px-4 py-2 text-sm border-b-2 -mb-px {{ $tab === $key ? 'border-brand text-brand font-medium' : 'border-transparent text-muted hover:text-slate-900' }}">
        {{ $heading }}
      </a>
    @endforeach
  </div>

  @if($tab === 'overview')
    @php
      $groups = [
        'Basic Info' => [
          'Legal Entity' => $principal->legal_entity_name,
          'Type' => $text($principal->principal_type),
          'Country' => $principal->country,
          'Status' => $text($principal->status),
          'Primary Contact' => $principal->primary_contact?->name,
          'Vessels' => count($vessels),
        ],
        'Business Terms' => [
          'Contract Start' => $fmt($principal->contract_start_date),
          'Contract End' => $fmt($principal->contract_end_date),
          'Contract Type' => $text($principal->contract_type),
          'Payment Terms' => $principal->payment_terms,
        ],
        'Compliance & Financial' => [
          'P&I Club' => $principal->p_and_i_club,
          'Wage Scale' => $principal->wage_scale_reference,
          'Tax ID' => $principal->tax_id,
          'Billing Address' => $principal->billing_address,
          'Bank Details' => $principal->bank_details,
        ],
      ];
    @endphp

    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
        <h2 class="text-sm font-semibold text-slate-900">Address &amp; Contact</h2>
        @if($principal->website)<span class="text-xs text-muted">{{ $principal->website }}</span>@endif
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($principal->addressRows() as $address)
          <div class="border border-line rounded-lg p-4 text-sm min-w-0">
            <span class="chip border bg-slate-50 text-slate-600 border-line">{{ $address['address_type'] ?? 'Address' }}</span>
            <p class="mt-2 text-slate-800 font-medium whitespace-pre-line">{{ collect([$address['address'] ?? null, implode(' ', array_filter([$address['city'] ?? null, $address['province'] ?? null, $address['postal_code'] ?? null])), $address['country'] ?? null])->filter()->implode("\n") ?: '—' }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
              @foreach(['phone' => 'Phone', 'fax' => 'Fax', 'email' => 'Email', 'wechat' => 'WeChat'] as $key => $name)
                @if(filled($address[$key] ?? null))
                  <dt class="text-muted">{{ $name }}</dt><dd class="text-slate-700 truncate">{{ $address[$key] }}</dd>
                @endif
              @endforeach
            </dl>
          </div>
        @empty
          <p class="text-sm text-muted">No address yet.</p>
        @endforelse
      </div>
    </div>

    @foreach($groups as $group => $rows)
      <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ $group }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
          @foreach($rows as $labelText => $value)
            <div>
              <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
              <div class="text-slate-800 mt-1 font-medium">{{ filled($value) ? $value : '—' }}</div>
            </div>
          @endforeach
        </div>
        @if($group === 'Business Terms')
          <div class="mt-6 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-[11px] uppercase text-muted tracking-wider border-b border-line">
                  <th class="text-left py-2 font-medium">Manning Fee Type</th>
                  <th class="text-right py-2 font-medium">Amount</th>
                  <th class="text-left px-3 py-2 font-medium">Currency</th>
                  <th class="text-left py-2 font-medium">Description</th>
                </tr>
              </thead>
              <tbody>
                @forelse($principal->feeRows() as $fee)
                  <tr class="border-t border-line">
                    <td class="py-2 text-slate-800 font-medium">{{ $text($fee['fee_type'] ?? null) }}</td>
                    <td class="py-2 text-right tabular-nums text-slate-800">{{ filled($fee['amount'] ?? null) ? number_format((float) $fee['amount'], 2) : '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ $fee['currency'] ?? '—' }}</td>
                    <td class="py-2 text-slate-600">{{ $fee['description'] ?? '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="py-4 text-center text-muted">No manning fee yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        @endif
      </div>
    @endforeach
  @endif

  @if($tab === 'vessels')
    <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
              <th class="text-left px-5 py-3 font-medium">Vessel</th>
              <th class="text-left px-3 py-3 font-medium">IMO</th>
              <th class="text-left px-3 py-3 font-medium">Type</th>
              <th class="text-left px-3 py-3 font-medium">Company</th>
              <th class="text-right px-5 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($vessels as $vessel)
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-5 py-3 text-slate-900 font-medium">{{ $vessel['vessel_name'] ?? $vessel['name'] }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $vessel['imo_number'] ?? '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $vessel['vessel_type'] ?? '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $vessel['company'] ?? '—' }}</td>
                <td class="px-5 py-3 text-right">
                  <a href="{{ route('vessels.show', $vessel['name']) }}" class="text-brand hover:text-brand-d font-medium">View</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-5 py-10 text-center text-muted">
                No vessels linked yet. Set this principal on a vessel to see it here.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($tab === 'contacts')
    <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
              <th class="text-left px-5 py-3 font-medium">Name</th>
              <th class="text-left px-3 py-3 font-medium">Position</th>
              <th class="text-left px-3 py-3 font-medium">Email</th>
              <th class="text-left px-3 py-3 font-medium">Phone</th>
              <th class="text-left px-3 py-3 font-medium">WhatsApp</th>
              <th class="text-left px-3 py-3 font-medium">WeChat</th>
              <th class="text-left px-3 py-3 font-medium">Primary</th>
            </tr>
          </thead>
          <tbody>
            @forelse($principal->contactPersons as $contact)
              <tr class="border-t border-line">
                <td class="px-5 py-3 text-slate-900 font-medium">{{ $contact->name }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $contact->position ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $contact->email ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $contact->phone ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $contact->whatsapp ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $contact->wechat ?: '—' }}</td>
                <td class="px-3 py-3">
                  @if($contact->is_primary)
                    <span class="chip border bg-brand/10 text-brand border-brand/20">Primary</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="px-5 py-10 text-center text-muted">No contact persons yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($tab === 'notes')
    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <p class="text-sm text-slate-700 whitespace-pre-line">{{ $principal->notes ?: 'No notes.' }}</p>
    </div>
  @endif

  <p class="text-[11px] text-muted">
    Created {{ $principal->created_at?->format('d M Y H:i') }} by {{ $principal->created_by ?? '—' }} ·
    Updated {{ $principal->updated_at?->format('d M Y H:i') }} by {{ $principal->updated_by ?? '—' }}
    @if($principal->erpnext_name) · ERP HPY: <span class="font-mono">{{ $principal->erpnext_name }}</span> @endif
  </p>
@endsection
