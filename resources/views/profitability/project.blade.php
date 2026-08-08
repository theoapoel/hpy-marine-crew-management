@extends('layouts.app')

@section('title', (($project->project_name ?? null) ?: $project->name) . ' · Profitability · HPYMarine')
@section('heading', 'Vessel Profitability')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      @if($project->vessel ?? null)
        <a href="{{ route('profitability.vessel', $project->vessel) }}" class="text-xs text-muted hover:text-slate-700">&larr; Back to vessel</a>
      @else
        <a href="{{ route('profitability.index') }}" class="text-xs text-muted hover:text-slate-700">&larr; All vessels</a>
      @endif
      <h1 class="text-2xl font-semibold text-slate-900 mt-1">{{ ($project->project_name ?? null) ?: $project->name }}</h1>
      <p class="text-sm text-muted mt-1">
        {{ $project->name }}
        @if($project->vessel ?? null) · {{ $project->vessel }} @endif
        @if($project->customer ?? null) · {{ $project->customer }} @endif
        @if($project->status ?? null) · {{ $project->status }} @endif
      </p>
    </div>
  </div>

  @include('profitability.partials.summary')
  @include('profitability.partials.components')

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="px-5 py-3 border-b border-line flex items-baseline justify-between">
      <h2 class="text-sm font-semibold text-slate-900">Documents behind these figures</h2>
      <span class="text-[11px] text-muted">{{ $lines->count() }} lines · submitted only</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Date</th>
            <th class="text-left px-3 py-3 font-medium">Document</th>
            <th class="text-left px-3 py-3 font-medium">Party / Account</th>
            <th class="text-left px-3 py-3 font-medium">Description</th>
            <th class="text-left px-3 py-3 font-medium">Component</th>
            <th class="text-right px-5 py-3 font-medium">Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse($lines as $line)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $line['date'] ?: '—' }}</td>
              <td class="px-3 py-3">
                <div class="text-slate-900">{{ $line['document'] }}</div>
                <div class="text-[11px] text-muted">{{ $line['doctype'] }}</div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $line['party'] ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $line['description'] ?: '—' }}</td>
              <td class="px-3 py-3">
                <span class="chip {{ $line['kind'] === 'revenue' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                  {{ $line['component'] }}
                </span>
              </td>
              <td class="px-5 py-3 text-right font-medium tabular-nums {{ $line['kind'] === 'revenue' ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $line['kind'] === 'revenue' ? '+' : '−' }}{{ number_format(round(abs($line['amount']))) }}
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-muted">Nothing posted against this project yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
