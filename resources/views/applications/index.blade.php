@extends('layouts.app')

@section('title', 'Recruitment Pipeline · HPYMarine')
@section('heading', 'Recruitment Pipeline')

@section('content')
  @php
    $text = fn ($v) => ucwords(str_replace('_', ' ', (string) $v));
    $stageColors = [
      'applied'   => 'bg-slate-100 text-slate-600 border-slate-200',
      'screening' => 'bg-blue-50 text-blue-700 border-blue-200',
      'interview' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
      'mcu'       => 'bg-amber-50 text-amber-700 border-amber-200',
      'offer'     => 'bg-violet-50 text-violet-700 border-violet-200',
      'hired'     => 'bg-green-50 text-green-700 border-green-200',
      'rejected'  => 'bg-rose-50 text-rose-700 border-rose-200',
      'withdrawn' => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Recruitment Pipeline</h1>
      <p class="text-sm text-muted mt-1">{{ $applications->count() }} lamaran</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ request()->fullUrlWithQuery(['view' => $view === 'board' ? 'list' : 'board']) }}"
         class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">
        {{ $view === 'board' ? 'Tampilan List' : 'Tampilan Board' }}
      </a>
      @can('applications.create')
        <a href="{{ route('applications.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Lamaran Baru</a>
      @endcan
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="hidden" name="view" value="{{ $view }}">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari kandidat atau kode..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="stage" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua tahap</option>
      @foreach(array_merge($stages, $closedStages) as $stage)
        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $text($stage) }}</option>
      @endforeach
    </select>
    <select name="applied_rank" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua rank</option>
      @foreach($ranks as $rank)<option value="{{ $rank }}" @selected(($filters['applied_rank'] ?? '') === $rank)>{{ $rank }}</option>@endforeach
    </select>
    <select name="vessel" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua kapal</option>
      @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected(($filters['vessel'] ?? '') === $vessel)>{{ $vessel }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  @if($view === 'board')
    {{-- Board: one column per stage --}}
    <div class="overflow-x-auto pb-2">
      <div class="flex gap-4 min-w-max">
        @foreach($stages as $stage)
          @php $cards = $byStage[$stage] ?? collect(); @endphp
          <div class="w-64 shrink-0">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">{{ $text($stage) }}</span>
              <span class="text-[11px] text-muted">{{ $cards->count() }}</span>
            </div>
            <div class="space-y-2">
              @forelse($cards as $application)
                <div class="bg-panel border border-line rounded-lg p-3 shadow-sm">
                  <a href="{{ route('applications.show', $application) }}" class="text-sm font-medium text-slate-900 hover:text-brand">
                    {{ $application->candidate?->full_name ?? '—' }}
                  </a>
                  <div class="text-[11px] text-muted mt-0.5">{{ $application->applied_rank ?: '—' }}{{ $application->vessel ? ' · ' . $application->vessel : '' }}</div>
                  <div class="text-[11px] text-muted mt-1 font-mono">{{ $application->application_code }}</div>
                  <div class="text-[11px] text-muted">{{ $application->days_in_pipeline }} hari di pipeline</div>

                  @can('applications.update')
                    @if($application->next_stage)
                      <form method="POST" action="{{ route('applications.advance', $application) }}" class="mt-2">
                        @csrf
                        <button class="w-full text-[11px] bg-brand/10 text-brand hover:bg-brand/20 rounded px-2 py-1 font-medium">
                          → {{ $text($application->next_stage) }}
                        </button>
                      </form>
                    @endif
                  @endcan
                </div>
              @empty
                <div class="text-[11px] text-muted border border-dashed border-line rounded-lg p-3 text-center">kosong</div>
              @endforelse
            </div>
          </div>
        @endforeach

        {{-- Closed applications, parked at the end --}}
        <div class="w-64 shrink-0">
          <div class="text-[11px] uppercase tracking-wider text-muted font-semibold mb-2">Ditutup</div>
          <div class="space-y-2">
            @foreach($closedStages as $stage)
              @foreach($byStage[$stage] ?? [] as $application)
                <div class="bg-panel border border-line rounded-lg p-3 shadow-sm opacity-70">
                  <a href="{{ route('applications.show', $application) }}" class="text-sm font-medium text-slate-900 hover:text-brand">
                    {{ $application->candidate?->full_name ?? '—' }}
                  </a>
                  <div class="mt-1"><span class="chip border {{ $stageColors[$stage] }}">{{ $text($stage) }}</span></div>
                  @if($application->rejection_reason)
                    <div class="text-[11px] text-muted mt-1">{{ $application->rejection_reason }}</div>
                  @endif
                </div>
              @endforeach
            @endforeach
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
              <th class="text-left px-5 py-3 font-medium">Kode</th>
              <th class="text-left px-3 py-3 font-medium">Kandidat</th>
              <th class="text-left px-3 py-3 font-medium">Rank</th>
              <th class="text-left px-3 py-3 font-medium">Kapal</th>
              <th class="text-left px-3 py-3 font-medium">Tahap</th>
              <th class="text-left px-3 py-3 font-medium">Applied</th>
              <th class="text-right px-5 py-3 font-medium">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($applications as $application)
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-5 py-3 font-mono text-[12px] text-muted">{{ $application->application_code }}</td>
                <td class="px-3 py-3">
                  <a href="{{ route('applications.show', $application) }}" class="text-slate-900 font-medium hover:text-brand">
                    {{ $application->candidate?->full_name ?? '—' }}
                  </a>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $application->applied_rank ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $application->vessel ?: '—' }}</td>
                <td class="px-3 py-3"><span class="chip border {{ $stageColors[$application->stage] ?? '' }}">{{ $text($application->stage) }}</span></td>
                <td class="px-3 py-3 text-slate-600">{{ $application->applied_date?->format('d M Y') }}</td>
                <td class="px-5 py-3 text-right">
                  <a href="{{ route('applications.show', $application) }}" class="text-brand hover:text-brand-d font-medium">Buka</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="px-5 py-10 text-center text-muted">Belum ada lamaran.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
