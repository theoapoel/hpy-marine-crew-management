@extends('layouts.app')

@section('title', $label . ' · Profitability · HPYMarine')
@section('heading', 'Vessel Profitability')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <a href="{{ route('profitability.index') }}" class="text-xs text-muted hover:text-slate-700">&larr; All vessels</a>
      <h1 class="text-2xl font-semibold text-slate-900 mt-1">{{ $label }}</h1>
      <p class="text-sm text-muted mt-1">{{ $projects->count() }} projects · one per contract, charter or voyage</p>
    </div>
    <a href="{{ route('vessels.show', $vessel) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Vessel profile</a>
  </div>

  @include('profitability.partials.period')
  @include('profitability.partials.summary')

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="px-5 py-3 border-b border-line">
      <h2 class="text-sm font-semibold text-slate-900">Projects</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Project</th>
            <th class="text-left px-3 py-3 font-medium">Customer</th>
            <th class="text-left px-3 py-3 font-medium">Period</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-right px-3 py-3 font-medium">Revenue</th>
            <th class="text-right px-3 py-3 font-medium">Cost</th>
            <th class="text-right px-3 py-3 font-medium">Margin</th>
            <th class="text-right px-5 py-3 font-medium">Margin %</th>
          </tr>
        </thead>
        <tbody>
          @forelse($projects as $project)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3">
                <a href="{{ route('profitability.project', $project['name']) }}" class="text-slate-900 font-medium hover:text-brand">
                  {{ ($project['project_name'] ?? null) ?: $project['name'] }}
                </a>
                <div class="text-[11px] text-muted">{{ $project['name'] }}</div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ ($project['customer'] ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">
                {{ ($project['expected_start_date'] ?? null) ?: '—' }} &rarr; {{ ($project['expected_end_date'] ?? null) ?: '—' }}
              </td>
              <td class="px-3 py-3 text-slate-600">{{ ($project['status'] ?? null) ?: '—' }}</td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">{{ number_format(round($project['revenue'])) }}</td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">{{ number_format(round($project['cost'])) }}</td>
              <td class="px-3 py-3 text-right font-medium tabular-nums {{ $project['margin'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ number_format(round($project['margin'])) }}
              </td>
              <td class="px-5 py-3 text-right text-slate-600 tabular-nums">
                {{ $project['margin_pct'] === null ? '—' : $project['margin_pct'] . '%' }}
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">No projects linked to this vessel yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @include('profitability.partials.components')
@endsection
