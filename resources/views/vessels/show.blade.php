@extends('layouts.app')

@section('title', ($vessel->vessel_name ?? $vessel->name) . ' · HPYMarine')
@section('heading', 'Vessels')

@section('content')
  @php
    $file = fn ($path) => route('erp.file', ['path' => $path]);
    $fmt = function ($value) {
        if (blank($value)) { return '—'; }
        try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y'); }
        catch (\Throwable) { return $value; }
    };
    $num = fn ($v) => filled($v) && (float) $v ? number_format((float) $v, 2) : '—';
    $certColors = [
      'Valid' => 'bg-green-50 text-green-700 border-green-200',
      'Expiring Soon' => 'bg-amber-50 text-amber-700 border-amber-200',
      'Expired' => 'bg-rose-50 text-rose-700 border-rose-200',
      'Revoked' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div class="flex items-start gap-4">
      @if($vessel->vessel_photo ?? null)
        <img src="{{ route('erp.file', ['path' => $vessel->vessel_photo]) }}" alt="{{ $vessel->vessel_name ?? $vessel->name }}"
             class="w-32 h-24 rounded-xl object-cover border border-line">
      @endif
      <div>
      <a href="{{ route('vessels.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Fleet</a>
      <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $vessel->vessel_name ?? $vessel->name }}</h1>
      <p class="text-sm text-muted mt-1">
        {{ ($vessel->vessel_type ?? null) ?: '—' }}
        {{ !empty($vessel->imo_number) ? ' · IMO ' . $vessel->imo_number : '' }}
        {{ !empty($vessel->flag_state) ? ' · ' . $vessel->flag_state : '' }}
      </p>
      @if($vessel->principal ?? null)
        <p class="text-sm text-muted">Principal: <span class="text-slate-700 font-medium">{{ $vessel->principal }}</span></p>
      @endif
      @if($vessel->ga_drawing ?? null)
        <a href="{{ route('erp.file', ['path' => $vessel->ga_drawing]) }}" target="_blank" rel="noopener"
           class="text-xs text-brand hover:underline">GA Drawing</a>
      @endif
      </div>
    </div>

    <div class="flex items-center gap-2">
      @can('vessels.update')
        <a href="{{ route('vessels.edit', $vessel->name) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
      @endcan
      @can('vessels.delete')
        <form method="POST" action="{{ route('vessels.destroy', $vessel->name) }}"
              onsubmit="return confirm('Delete {{ $vessel->vessel_name ?? $vessel->name }} from ERP HPY?')">
          @csrf @method('DELETE')
          <button class="text-sm text-rose-600 hover:text-rose-700 border border-line rounded-md px-4 py-2">Delete</button>
        </form>
      @endcan
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  @php
    $groups = [
      'Identification' => [
        'Vessel Name' => $vessel->vessel_name ?? null,
        'IMO Number' => $vessel->imo_number ?? null,
        'MMSI' => $vessel->mmsi_number ?? null,
        'Call Sign' => $vessel->call_sign ?? null,
        'Official Number' => $vessel->official_number ?? null,
        'Ex-Name' => $vessel->ex_name ?? null,
      ],
      'Registration & Class' => [
        'Company' => $vessel->company ?? null,
        'Principal' => $vessel->principal ?? null,
        'Flag State' => $vessel->flag_state ?? null,
        'Port of Registry' => $vessel->port_of_registry ?? null,
        'Date of Registration' => $fmt($vessel->date_of_registration ?? null),
        'Classification Society' => $vessel->classification_society ?? null,
        'Class Number' => $vessel->class_number ?? null,
        'Class Notation' => $vessel->class_notation ?? null,
      ],
      'Specification' => [
        'Vessel Type' => $vessel->vessel_type ?? null,
        'Sub-Type' => $vessel->sub_type ?? null,
        'Year Built' => $vessel->year_built ?? null,
        'Builder' => $vessel->builder ?? null,
        'Country of Build' => $vessel->country_of_build ?? null,
        'Delivery Date' => $fmt($vessel->delivery_date ?? null),
      ],
      'Dimensions' => [
        'LOA (m)' => $num($vessel->length_overall ?? null),
        'LBP (m)' => $num($vessel->length_bp ?? null),
        'Breadth (m)' => $num($vessel->breadth ?? null),
        'Depth Moulded (m)' => $num($vessel->depth_moulded ?? null),
        'Summer Draft (m)' => $num($vessel->draft_summer ?? null),
        'Freeboard (m)' => $num($vessel->freeboard ?? null),
      ],
      'Tonnage & Capacity' => [
        'Gross Tonnage' => $num($vessel->gross_tonnage ?? null),
        'Net Tonnage' => $num($vessel->net_tonnage ?? null),
        'Deadweight' => $num($vessel->deadweight ?? null),
        'Cargo Capacity (m³)' => $num($vessel->cargo_capacity ?? null),
        'Fuel Capacity (MT)' => $num($vessel->fuel_capacity ?? null),
        'Fresh Water (MT)' => $num($vessel->fresh_water_capacity ?? null),
      ],
    ];
  @endphp

  <div data-tab-group>
    <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
      @foreach(array_keys($groups) as $groupName)
        <button type="button" data-tab-target="{{ Str::slug($groupName) }}"
                class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">{{ $groupName }}</button>
      @endforeach
      <button type="button" data-tab-target="certificates" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Certificates</button>
      <button type="button" data-tab-target="crew" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Crew Onboard</button>
      <button type="button" data-tab-target="joining" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Joining Soon</button>
      <button type="button" data-tab-target="manning" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Manning</button>
    </div>

  @foreach($groups as $group => $rows)
    <div data-tab-panel="{{ Str::slug($group) }}" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ $group }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
        @foreach($rows as $labelText => $value)
          <div>
            <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
            <div class="text-slate-800 mt-1 font-medium">{{ filled($value) ? $value : '—' }}</div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach

  {{-- Certificates from the vessel's own child table --}}
  <div data-tab-panel="certificates" class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
    <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-4">Vessel Certificates</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
            <th class="text-left px-6 py-3 font-medium">Type</th>
            <th class="text-left px-3 py-3 font-medium">Number</th>
            <th class="text-left px-3 py-3 font-medium">Diterbitkan</th>
            <th class="text-left px-3 py-3 font-medium">Issued</th>
            <th class="text-left px-3 py-3 font-medium">Expiry</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-right px-6 py-3 font-medium">File</th>
          </tr>
        </thead>
        <tbody>
          @forelse($certificates as $certificate)
            <tr class="border-b border-line last:border-0">
              <td class="px-6 py-3 text-slate-900 font-medium">{{ $certificate->certificate_type ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $certificate->certificate_number ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $certificate->issued_by ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($certificate->issue_date ?? null) }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($certificate->expiry_date ?? null) }}</td>
              <td class="px-3 py-3">
                <span class="chip border {{ $certColors[$certificate->status ?? ''] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                  {{ $certificate->status ?? '—' }}
                </span>
              </td>
              <td class="px-6 py-3 text-right">
                @if(!empty($certificate->attachment))
                  <a href="{{ $file($certificate->attachment) }}" target="_blank" rel="noopener" class="text-brand hover:text-brand-d font-medium">Download</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-muted">No vessel certificates yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Crew currently aboard, from Crew Assignment --}}
  <div data-tab-panel="crew" class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
    <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-4">Crew Onboard ({{ $crew->count() }})</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
            <th class="text-left px-6 py-3 font-medium">Name</th>
            <th class="text-left px-3 py-3 font-medium">Rank</th>
            <th class="text-left px-3 py-3 font-medium">Sign On</th>
            <th class="text-left px-3 py-3 font-medium">Contract Until</th>
            <th class="text-left px-3 py-3 font-medium">Days Aboard</th>
          </tr>
        </thead>
        <tbody>
          @forelse($crew as $assignment)
            <tr class="border-b border-line last:border-0">
              <td class="px-6 py-3">
                <a href="{{ route('assignments.show', $assignment) }}" class="text-slate-900 font-medium hover:text-brand">{{ $assignment->crew_name }}</a>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->rank ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->sign_on_date?->format('d M Y') ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->planned_sign_off_date?->format('d M Y') ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->days_onboard ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-6 py-10 text-center text-muted">No crew aboard this vessel.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Booked on this ship, waiting to board --}}
  <div data-tab-panel="joining">
    <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-4">Joining Soon ({{ $planned->count() }})</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
              <th class="text-left px-6 py-3 font-medium">Name</th>
              <th class="text-left px-3 py-3 font-medium">Rank</th>
              <th class="text-left px-3 py-3 font-medium">Planned Sign On</th>
              <th class="text-right px-6 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($planned as $assignment)
              <tr class="border-b border-line last:border-0">
                <td class="px-6 py-3">
                  <a href="{{ route('assignments.show', $assignment) }}" class="text-slate-900 font-medium hover:text-brand">{{ $assignment->crew_name }}</a>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->rank ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->planned_sign_on_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-6 py-3 text-right">
                  @can('assignments.update')
                    <form method="POST" action="{{ route('assignments.do-sign-on', $assignment) }}" class="inline">
                      @csrf
                      <button class="text-brand hover:text-brand-d font-medium">Sign On</button>
                    </form>
                  @endcan
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div data-tab-panel="manning">
    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-900 mb-4">Manning Requirement</h2>
      <div class="flex flex-wrap gap-4 text-sm">
        @foreach($manning as $row)
          <div class="border border-line rounded-lg px-3 py-2">
            <div class="text-slate-800 font-medium">{{ $row->rank ?? $row->rank_name ?? '—' }}</div>
            <div class="text-[11px] text-muted">{{ $row->quantity ?? $row->required_qty ?? '—' }} crew</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  </div>
@endsection
