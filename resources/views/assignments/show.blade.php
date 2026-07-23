@extends('layouts.app')

@section('title', $assignment->assignment_code . ' · HPYMarine')
@section('heading', 'Crew Assignment')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $fmt = fn ($d) => $d ? $d->format('d M Y') : '—';
    $colors = [
      'planned'    => 'bg-blue-50 text-blue-700 border-blue-200',
      'onboard'    => 'bg-green-50 text-green-700 border-green-200',
      'signed_off' => 'bg-slate-100 text-slate-600 border-slate-200',
      'cancelled'  => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <a href="{{ route('assignments.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Crew Assignment</a>
      <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $assignment->crew_name }}</h1>
      <p class="text-sm text-muted mt-1">
        <span class="font-mono">{{ $assignment->assignment_code }}</span> ·
        {{ $assignment->rank ?: '—' }} · {{ $assignment->vessel ?: 'kapal belum ditentukan' }}
      </p>
      <div class="mt-2">
        <span class="chip border {{ $colors[$assignment->status] ?? '' }}">{{ $text($assignment->status) }}</span>
        @if($assignment->is_overdue)<span class="chip border bg-rose-50 text-rose-700 border-rose-200 ml-1">lewat kontrak</span>@endif
      </div>
    </div>
    <div class="flex items-center gap-2">
      @can('assignments.update')
        <a href="{{ route('assignments.edit', $assignment) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
        @if($assignment->status === 'planned')
          <form method="POST" action="{{ route('assignments.do-sign-on', $assignment) }}">
            @csrf
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Sign On sekarang</button>
          </form>
        @elseif($assignment->status === 'onboard')
          <form method="POST" action="{{ route('assignments.do-sign-off', $assignment) }}">
            @csrf
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Sign Off sekarang</button>
          </form>
        @endif
      @endcan
    </div>
  </div>

  @php
    $rows = [
      'Employee (ERP HPY)' => $assignment->employee_id,
      'Kandidat' => $assignment->candidate?->candidate_code,
      'Lamaran' => $assignment->application?->application_code,
      'Kapal' => $assignment->vessel,
      'Rank' => $assignment->rank,
      'Rencana Sign On' => $fmt($assignment->planned_sign_on_date),
      'Sign On' => $fmt($assignment->sign_on_date),
      'Pelabuhan Sign On' => $assignment->sign_on_port,
      'Kontrak' => $assignment->contract_months ? $assignment->contract_months . ' bulan' : null,
      'Rencana Sign Off' => $fmt($assignment->planned_sign_off_date),
      'Sign Off' => $fmt($assignment->sign_off_date),
      'Pelabuhan Sign Off' => $assignment->sign_off_port,
      'Alasan Sign Off' => $assignment->sign_off_reason,
      'Hari di Kapal' => $assignment->days_onboard !== null ? $assignment->days_onboard . ' hari' : null,
      'Upah' => $assignment->wage ? $assignment->wage_currency . ' ' . number_format((float) $assignment->wage, 2) : null,
    ];
  @endphp

  <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">Detail</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
      @foreach($rows as $labelText => $value)
        <div>
          <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
          <div class="text-slate-800 mt-1 font-medium">
            @if($labelText === 'Employee (ERP HPY)' && filled($value))
              <a href="{{ route('crew.show', $value) }}" class="text-brand hover:underline">{{ $value }}</a>
            @elseif($labelText === 'Kapal' && filled($value))
              <a href="{{ route('vessels.show', $value) }}" class="text-brand hover:underline">{{ $value }}</a>
            @else
              {{ filled($value) ? $value : '—' }}
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

  @if($assignment->notes)
    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-900 mb-2">Catatan</h2>
      <p class="text-sm text-slate-700 whitespace-pre-line">{{ $assignment->notes }}</p>
    </div>
  @endif
@endsection
