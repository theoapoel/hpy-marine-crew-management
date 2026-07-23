@extends('layouts.app')

@section('title', 'Sign On · HPYMarine')
@section('heading', 'Sign On')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Sign On</h1>
      <p class="text-sm text-muted mt-1">{{ $assignments->count() }} crew menunggu naik kapal</p>
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama crew..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="vessel" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua kapal</option>
      @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected(($filters['vessel'] ?? '') === $vessel)>{{ $vessel }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="space-y-3">
    @forelse($assignments as $assignment)
      <div class="bg-panel border border-line rounded-xl p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[220px]">
            <a href="{{ route('assignments.show', $assignment) }}" class="text-sm font-medium text-slate-900 hover:text-brand">{{ $assignment->crew_name }}</a>
            <div class="text-[11px] text-muted">
              {{ $assignment->assignment_code }} · {{ $assignment->rank ?: '—' }} · {{ $assignment->vessel ?: 'kapal belum ditentukan' }}
            </div>
            @if($assignment->planned_sign_on_date)
              <div class="text-[11px] text-muted">Rencana: {{ $assignment->planned_sign_on_date->format('d M Y') }}</div>
            @endif
          </div>

          @can('assignments.update')
            <form method="POST" action="{{ route('assignments.do-sign-on', $assignment) }}" class="flex flex-wrap items-end gap-2 ml-auto">
              @csrf
              <div>
                <label class="block text-xs text-muted mb-1">Tanggal Sign On</label>
                <input type="date" name="sign_on_date" value="{{ now()->format('Y-m-d') }}"
                       class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Pelabuhan</label>
                <input type="text" name="sign_on_port" class="w-36 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Kontrak (bulan)</label>
                <input type="number" name="contract_months" min="1" max="36" value="{{ $assignment->contract_months ?: 6 }}"
                       class="w-24 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2">Sign On</button>
            </form>
          @endcan
        </div>
      </div>
    @empty
      <div class="bg-panel border border-line rounded-xl p-10 text-center text-muted text-sm shadow-sm">
        Tidak ada crew yang menunggu sign on.
      </div>
    @endforelse
  </div>

  <p class="text-[11px] text-muted">Sign on juga memperbarui Employee di ERP HPY: status crew jadi Onboard, kapal dan tanggal kontrak ikut terisi.</p>
@endsection
