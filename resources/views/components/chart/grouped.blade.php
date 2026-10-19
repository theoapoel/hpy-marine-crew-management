{{--
  Grouped column chart for money, drawn by ui.js (columnCharts): one group per period,
  one column per series, a zero line so losses hang below it.

  labels:    ['Jan 2026', ...]
  series:    [['name' => ..., 'color' => hex, 'values' => [number, ...]], ...]
  diverging: colour a single series by sign (gain green / loss red)
  notes:     optional extra tooltip line per label
--}}
@props(['labels' => [], 'series' => [], 'diverging' => false, 'notes' => [], 'label' => 'Chart'])

@php
  $money = fn ($v) => \App\Services\Erpnext\AccountingCharts::money((float) $v);
  $payload = ['labels' => array_values($labels), 'series' => array_values($series), 'diverging' => $diverging, 'notes' => array_values($notes)];
@endphp

<div data-column-chart='@json($payload)' {{ $attributes }}>
  @if(count($series) > 1)
    <div class="flex flex-wrap gap-x-4 gap-y-1 mb-3 text-xs text-slate-600">
      @foreach($series as $s)
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px]" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>
      @endforeach
    </div>
  @elseif($diverging)
    <div class="flex flex-wrap gap-x-4 gap-y-1 mb-3 text-xs text-slate-600">
      <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[#1e8e3e]"></span>▲ Profit</span>
      <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[#d93025]"></span>▼ Loss</span>
    </div>
  @endif

  <div data-column-plot role="img" aria-label="{{ $label }}" tabindex="0"
       class="relative h-56 lg:h-64 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand/40"></div>
</div>

@php
  $hasNotes = collect($notes)->contains(fn ($n) => filled($n));

  $tableColumns = collect([['label' => 'Period', 'align' => 'left']])
    ->concat(collect($series)->map(fn ($s) => ['label' => $s['name'], 'align' => 'right']))
    ->when($hasNotes, fn ($c) => $c->push(['label' => 'Note', 'align' => 'left']))
    ->all();

  $tableRows = collect($labels)->values()->map(function ($l, $i) use ($series, $notes, $money, $hasNotes) {
    $cells = [$l];
    foreach ($series as $s) {
      $cells[] = $money($s['values'][$i] ?? 0);
    }
    if ($hasNotes) {
      $cells[] = $notes[$i] ?? '';
    }
    return $cells;
  })->all();

  $tableTotal = ['Total'];
  foreach ($series as $s) {
    $tableTotal[] = $money(array_sum($s['values']));
  }
  if ($hasNotes) {
    $tableTotal[] = '';
  }
@endphp

<x-chart.table :columns="$tableColumns" :rows="$tableRows" :total="$tableTotal" :label="$label" />
