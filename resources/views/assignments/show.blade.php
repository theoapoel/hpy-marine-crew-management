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
        {{ $assignment->rank ?: '—' }} · {{ $assignment->vessel ?: 'vessel not set' }}
      </p>
      <div class="mt-2">
        <span class="chip border {{ $colors[$assignment->status] ?? '' }}">{{ $text($assignment->status) }}</span>
        @if($assignment->is_overdue)<span class="chip border bg-rose-50 text-rose-700 border-rose-200 ml-1">past contract</span>@endif
      </div>
    </div>
    <div class="flex items-center gap-2">
      @can('assignments.update')
        <a href="{{ route('assignments.edit', $assignment) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
        @if($assignment->status === 'planned')
          <form method="POST" action="{{ route('assignments.do-sign-on', $assignment) }}">
            @csrf
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Sign On now</button>
          </form>
        @elseif($assignment->status === 'onboard')
          <form method="POST" action="{{ route('assignments.do-sign-off', $assignment) }}">
            @csrf
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Sign Off now</button>
          </form>
        @endif
      @endcan
    </div>
  </div>

  @php
    $rows = [
      'Employee (ERP HPY)' => $assignment->employee_id,
      'Candidate' => $assignment->candidate?->candidate_code,
      'Application' => $assignment->application?->application_code,
      'Vessel' => $assignment->vessel,
      'Rank' => $assignment->rank,
      'Planned Sign On' => $fmt($assignment->planned_sign_on_date),
      'Sign On' => $fmt($assignment->sign_on_date),
      'Sign On Port' => $assignment->sign_on_port,
      'Contract' => $assignment->contract_months ? $assignment->contract_months . ' months' : null,
      'Planned Sign Off' => $fmt($assignment->planned_sign_off_date),
      'Sign Off' => $fmt($assignment->sign_off_date),
      'Sign Off Port' => $assignment->sign_off_port,
      'Sign Off Reason' => $assignment->sign_off_reason,
      'Days on Board' => $assignment->days_onboard !== null ? $assignment->days_onboard . ' days' : null,
      'Wage' => $assignment->wage ? $assignment->wage_currency . ' ' . number_format((float) $assignment->wage, 2) : null,
    ];
    $links = ['Employee (ERP HPY)', 'Candidate', 'Application'];
    $unlinked = blank($assignment->employee_id) || ! $assignment->candidate || ! $assignment->application;
  @endphp

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl px-4 py-3">{{ $errors->first() }}</div>
  @endif

  @if($unlinked)
    <div class="flex flex-wrap items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3">
      <span class="flex-1 min-w-60">
        @if(blank($assignment->employee_id))
          This assignment is not linked to an Employee in ERP HPY, so its changes do not reach Crew Master.
        @else
          Candidate / application not linked.
        @endif
      </span>
      @can('assignments.update')
        <form method="POST" action="{{ route('assignments.link', $assignment) }}">
          @csrf
          <button class="bg-white hover:bg-amber-100 border border-amber-300 text-amber-900 text-xs font-medium rounded-md px-3 py-1.5">Link automatically</button>
        </form>
      @endcan
    </div>
  @endif

  <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">Detail</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
      @foreach($rows as $labelText => $value)
        <div>
          <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
          <div class="text-slate-800 mt-1 font-medium">
            @if(in_array($labelText, $links, true) && blank($value))
              <span class="text-amber-700 font-normal">Not linked</span>
            @elseif($labelText === 'Employee (ERP HPY)')
              <a href="{{ route('crew.show', $value) }}" class="text-brand hover:underline">{{ $value }}</a>
            @elseif($labelText === 'Candidate')
              <a href="{{ route('candidates.show', $assignment->candidate) }}" class="text-brand hover:underline">{{ $value }}</a>
              <span class="text-muted font-normal">· {{ $assignment->candidate->full_name }}</span>
            @elseif($labelText === 'Application')
              <a href="{{ route('applications.show', $assignment->application) }}" class="text-brand hover:underline">{{ $value }}</a>
              <span class="text-muted font-normal">· {{ ucfirst($assignment->application->stage) }}</span>
            @elseif($labelText === 'Vessel' && filled($value))
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
      <h2 class="text-sm font-semibold text-slate-900 mb-2">Notes</h2>
      <p class="text-sm text-slate-700 whitespace-pre-line">{{ $assignment->notes }}</p>
    </div>
  @endif
@endsection
