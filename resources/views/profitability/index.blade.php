@extends('layouts.app')

@section('title', 'Vessel Profitability · HPYMarine')
@section('heading', 'Vessel Profitability')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Vessel Profitability</h1>
      <p class="text-sm text-muted mt-1">
        {{ $rows->count() }} vessels · from the Project doctype in ERP HPY, in company currency
      </p>
    </div>
  </div>

  @include('profitability.partials.period')
  @include('profitability.partials.summary')

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Vessel</th>
            <th class="text-right px-3 py-3 font-medium">Projects</th>
            <th class="text-right px-3 py-3 font-medium">Revenue</th>
            <th class="text-right px-3 py-3 font-medium">Cost</th>
            <th class="text-right px-3 py-3 font-medium">Margin</th>
            <th class="text-right px-3 py-3 font-medium">Margin %</th>
            <th class="text-right px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3">
                <span class="text-slate-900 font-medium">{{ $row['label'] }}</span>
                @unless($row['vessel'])
                  <div class="text-[11px] text-muted">projects with no vessel set — fill in Project.vessel in ERP HPY</div>
                @endunless
              </td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">{{ $row['projects'] }}</td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">{{ number_format(round($row['revenue'])) }}</td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">{{ number_format(round($row['cost'])) }}</td>
              <td class="px-3 py-3 text-right font-medium tabular-nums {{ $row['margin'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ number_format(round($row['margin'])) }}
              </td>
              <td class="px-3 py-3 text-right text-slate-600 tabular-nums">
                {{ $row['margin_pct'] === null ? '—' : $row['margin_pct'] . '%' }}
              </td>
              <td class="px-5 py-3 text-right">
                @if($row['vessel'])
                  <a href="{{ route('profitability.vessel', $row['vessel']) }}" class="text-brand hover:text-brand-d font-medium">Open</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-5 py-10 text-center text-muted">
                No projects in ERP HPY yet. Run <code class="text-slate-700">php artisan erp:sync-project-fields</code>,
                then <code class="text-slate-700">php artisan erp:seed-vessel-profitability</code> for sample data.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @include('profitability.partials.components')
@endsection
