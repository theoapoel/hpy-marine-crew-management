@extends('layouts.app')

@section('title', 'Vessels · HPYMarine')
@section('heading', 'Vessels')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Fleet</h1>
      <p class="text-sm text-muted mt-1">{{ $vessels->count() }} vessels · from ERP HPY</p>
    </div>
    @can('vessels.create')
      <a href="{{ route('vessels.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add Vessel</a>
    @endcan
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search vessel name..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="vessel_type" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">All types</option>
      @foreach($types as $type)<option value="{{ $type }}" @selected(($filters['vessel_type'] ?? '') === $type)>{{ $type }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Vessel</th>
            <th class="text-left px-3 py-3 font-medium">Company</th>
            <th class="text-left px-3 py-3 font-medium">Principal</th>
            <th class="text-left px-3 py-3 font-medium">Type</th>
            <th class="text-left px-3 py-3 font-medium">IMO</th>
            <th class="text-left px-3 py-3 font-medium">Flag</th>
            <th class="text-left px-3 py-3 font-medium">Class</th>
            <th class="text-left px-3 py-3 font-medium">GT / DWT</th>
            <th class="text-left px-3 py-3 font-medium">Crew Onboard</th>
            <th class="text-right px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($vessels as $vessel)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  @if($vessel->vessel_photo)
                    <img src="{{ route('erp.file', ['path' => $vessel->vessel_photo]) }}" alt=""
                         class="w-12 h-9 rounded object-cover border border-line shrink-0">
                  @else
                    <div class="w-12 h-9 rounded bg-slate-100 text-muted flex items-center justify-center text-[10px] shrink-0">no photo</div>
                  @endif
                  <div>
                    <a href="{{ route('vessels.show', $vessel->name) }}" class="text-slate-900 font-medium hover:text-brand">{{ ($vessel->vessel_name ?? null) ?? $vessel->name }}</a>
                    <div class="text-[11px] text-muted">{{ $vessel->call_sign ?: '—' }}{{ !empty($vessel->year_built) ? ' · built ' . $vessel->year_built : '' }}</div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->company ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->principal ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->vessel_type ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->imo_number ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->flag_state ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ ($vessel->classification_society ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">
                {{ $vessel->gross_tonnage ? number_format((float) $vessel->gross_tonnage) : '—' }} /
                {{ $vessel->deadweight ? number_format((float) $vessel->deadweight) : '—' }}
              </td>
              <td class="px-3 py-3 text-slate-600">
                {{ $vessel->onboard }}
                @if($vessel->planned)
                  <span class="text-[11px] text-muted">(+{{ $vessel->planned }} joining)</span>
                @endif
              </td>
              <td class="px-5 py-3 text-right">
                <a href="{{ route('vessels.show', $vessel->name) }}" class="text-brand hover:text-brand-d font-medium">Open</a>
                @can('vessels.update')
                  <a href="{{ route('vessels.edit', $vessel->name) }}" class="text-slate-600 hover:text-slate-900 ml-3">Edit</a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="px-5 py-10 text-center text-muted">No vessels in ERP HPY yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
