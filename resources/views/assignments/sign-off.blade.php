@extends('layouts.app')

@section('title', 'Sign Off · HPYMarine')
@section('heading', 'Sign Off')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Sign Off</h1>
      <p class="text-sm text-muted mt-1">
        {{ $assignments->count() }} crew di atas kapal
        @if(($filters['days'] ?? 0) > 0) · kontrak habis dalam {{ $filters['days'] }} hari @endif
      </p>
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
    <select name="days" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="0">Semua yang onboard</option>
      @foreach([14, 30, 60, 90] as $days)
        <option value="{{ $days }}" @selected((int) ($filters['days'] ?? 0) === $days)>Kontrak habis ≤ {{ $days }} hari</option>
      @endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="space-y-3">
    @forelse($assignments as $assignment)
      <div class="bg-panel border border-line rounded-xl p-4 shadow-sm {{ $assignment->is_overdue ? 'border-rose-200' : '' }}">
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[240px]">
            <a href="{{ route('assignments.show', $assignment) }}" class="text-sm font-medium text-slate-900 hover:text-brand">{{ $assignment->crew_name }}</a>
            <div class="text-[11px] text-muted">
              {{ $assignment->assignment_code }} · {{ $assignment->rank ?: '—' }} · {{ $assignment->vessel ?: '—' }}
            </div>
            <div class="text-[11px] text-muted">
              Sign on {{ $assignment->sign_on_date?->format('d M Y') ?? '—' }} · {{ $assignment->days_onboard }} hari di atas kapal
              @if($assignment->planned_sign_off_date)
                · kontrak s/d {{ $assignment->planned_sign_off_date->format('d M Y') }}
              @endif
            </div>
            @if($assignment->is_overdue)
              <span class="chip border bg-rose-50 text-rose-700 border-rose-200 mt-1 inline-block">lewat kontrak</span>
            @endif
          </div>

          @can('assignments.update')
            <form method="POST" action="{{ route('assignments.do-sign-off', $assignment) }}" class="flex flex-wrap items-end gap-2 ml-auto">
              @csrf
              <div>
                <label class="block text-xs text-muted mb-1">Tanggal Sign Off</label>
                <input type="date" name="sign_off_date" value="{{ now()->format('Y-m-d') }}"
                       class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Pelabuhan</label>
                <input type="text" name="sign_off_port" class="w-36 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Alasan</label>
                <input type="text" name="sign_off_reason" placeholder="Kontrak selesai" class="w-44 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2">Sign Off</button>
            </form>
          @endcan
        </div>
      </div>
    @empty
      <div class="bg-panel border border-line rounded-xl p-10 text-center text-muted text-sm shadow-sm">
        Tidak ada crew yang sedang di atas kapal.
      </div>
    @endforelse
  </div>

  <p class="text-[11px] text-muted">Sign off mengosongkan kapal pada Employee di ERP HPY dan mengembalikan status crew ke Standby.</p>
@endsection
