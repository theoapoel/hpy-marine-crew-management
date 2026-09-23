@extends('layouts.app')

@section('title', $title . ' · HPYMarine')
@section('heading', $title)

@section('content')
  <div>
    <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
    <p class="text-sm text-muted mt-1">{{ $blurb }} · run by ERP HPY</p>
  </div>

  {{-- The four are read one after another, so they are tabs rather than four dead ends. --}}
  <div class="flex flex-wrap gap-1 border-b border-line">
    @foreach($reports as $key => $report)
      <a href="{{ route('accounting', $key) }}"
         class="px-4 py-2 text-sm rounded-t-lg -mb-px border-b-2 {{ $key === $slug ? 'border-brand text-brand font-medium bg-white' : 'border-transparent text-muted hover:text-slate-700' }}">
        {{ $report['title'] }}
      </a>
    @endforeach
  </div>

  <form method="GET" class="flex flex-wrap items-end gap-2">
    <label class="text-xs text-muted">
      <span class="block mb-1">From</span>
      <input type="date" name="from_date" value="{{ $input['from_date'] }}"
             class="bg-white border border-line rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    </label>
    <label class="text-xs text-muted">
      <span class="block mb-1">To</span>
      <input type="date" name="to_date" value="{{ $input['to_date'] }}"
             class="bg-white border border-line rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    </label>

    @if(in_array('account', $input['wanted'], true))
      <label class="text-xs text-muted">
        <span class="block mb-1">Account</span>
        <input type="text" name="account" value="{{ $input['account'] }}" list="accounts" placeholder="All accounts"
               class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
        <datalist id="accounts">@foreach($accounts as $account)<option value="{{ $account }}">@endforeach</datalist>
      </label>
    @endif

    @if(in_array('project', $input['wanted'], true))
      <label class="text-xs text-muted">
        <span class="block mb-1">Project</span>
        <select name="project" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
          <option value="">All projects</option>
          @foreach($projects as $project)<option value="{{ $project }}" @selected($input['project'] === $project)>{{ $project }}</option>@endforeach
        </select>
      </label>
    @endif

    @if(in_array('periodicity', $input['wanted'], true))
      <label class="text-xs text-muted">
        <span class="block mb-1">Columns</span>
        <select name="periodicity" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
          @foreach(['Monthly', 'Quarterly', 'Half-Yearly', 'Yearly'] as $option)
            <option value="{{ $option }}" @selected($input['periodicity'] === $option)>{{ $option }}</option>
          @endforeach
        </select>
      </label>
    @endif

    <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Run report</button>
  </form>

  @if($error)
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">
      ERP HPY could not run this report: {{ $error }}
    </div>
  @endif

  @if(!empty($kpis))
    @php $money = fn ($v) => \App\Services\Erpnext\AccountingCharts::money((float) $v); @endphp
    <section aria-label="Summary" class="grid grid-cols-2 xl:grid-cols-4 gap-4" data-enter>
      @foreach($kpis as $kpi)
        @php
          $number = $kpi['value'] ?? $kpi['percent'] ?? $kpi['count'] ?? null;
          $tone = !empty($kpi['signed']) && $number !== null ? ($number < 0 ? 'text-[#d93025]' : 'text-[#1e8e3e]') : 'text-slate-900';
        @endphp
        <div class="bg-panel border border-line rounded-xl p-4 shadow-sm min-w-0">
          <div class="flex items-center gap-1.5 text-xs text-muted">
            @isset($kpi['color'])<span class="size-2 rounded-[3px]" style="background: {{ $kpi['color'] }}"></span>@endisset
            <span class="truncate">{{ $kpi['label'] }}</span>
          </div>
          <div class="mt-1.5 text-xl lg:text-2xl font-semibold tabular-nums truncate {{ $tone }}"
               @isset($kpi['value']) title="Rp {{ number_format($kpi['value'], 2) }}" @endisset>
            @if(array_key_exists('percent', $kpi))
              {{ $kpi['percent'] === null ? '—' : (!empty($kpi['signed']) ? ($kpi['percent'] < 0 ? '▼ ' : '▲ ') : '') . number_format(abs($kpi['percent']), 1) . '%' }}
            @elseif(array_key_exists('count', $kpi))
              {{ number_format($kpi['count']) }}
            @else
              {{ (!empty($kpi['signed']) ? ($kpi['value'] < 0 ? '▼ ' : '▲ ') : '') . $money($kpi['value']) }}
            @endif
          </div>
          @isset($kpi['note'])
            <div class="mt-1 text-xs font-medium {{ !empty($kpi['ok']) ? 'text-[#1e8e3e]' : 'text-[#d93025]' }}">{{ !empty($kpi['ok']) ? '✓' : '!' }} {{ $kpi['note'] }}</div>
          @endisset
        </div>
      @endforeach
    </section>
  @endif

  @if(!empty($charts))
    <section aria-label="Charts" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      @foreach($charts as $chart)
        <div class="bg-panel border border-line rounded-xl p-5 shadow-sm min-w-0 {{ !empty($chart['wide']) ? 'lg:col-span-2' : '' }}">
          <h2 class="text-sm font-semibold text-slate-900 mb-3">{{ $chart['title'] }}</h2>
          @if($chart['type'] === 'columns')
            <x-chart.grouped :labels="$chart['labels']" :series="$chart['series']" :diverging="$chart['diverging'] ?? false"
                             :notes="$chart['notes'] ?? []" :label="$chart['title']" />
          @else
            <x-chart.bars :rows="$chart['rows']" unit="" :label="$chart['title']" stacked />
          @endif
          @isset($chart['note'])
            <p class="mt-3 text-[11px] text-muted">{{ $chart['note'] }}</p>
          @endisset
        </div>
      @endforeach
    </section>
  @endif

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="px-5 py-3 border-b border-line flex items-baseline justify-between">
      <h2 class="text-sm font-semibold text-slate-900">
        {{ $input['from_date'] }} &rarr; {{ $input['to_date'] }}
      </h2>
      <span class="text-[11px] text-muted">{{ count($rows) }} rows</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            @foreach($columns as $column)
              <th class="px-3 py-3 font-medium whitespace-nowrap {{ $column['numeric'] ? 'text-right' : 'text-left' }}">{{ $column['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr class="border-t border-line hover:bg-slate-50 {{ $row['bold'] ? 'bg-slate-50/60 font-medium text-slate-900' : 'text-slate-600' }}">
              @foreach($columns as $index => $column)
                @php $value = $row[$column['fieldname']] ?? null; @endphp
                <td class="px-3 py-2 {{ $column['numeric'] ? 'text-right tabular-nums whitespace-nowrap' : '' }}"
                    @if($index === 0 && $row['indent']) style="padding-left: {{ 0.75 + $row['indent'] * 1.25 }}rem" @endif>
                  @if($column['numeric'])
                    {{ filled($value) ? number_format((float) $value) : '' }}
                  @else
                    {{ $value }}
                  @endif
                </td>
              @endforeach
            </tr>
          @empty
            <tr>
              <td colspan="{{ max(count($columns), 1) }}" class="px-5 py-10 text-center text-muted">
                {{ $error ? 'Nothing to show.' : 'ERP HPY returned no rows for this period.' }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
