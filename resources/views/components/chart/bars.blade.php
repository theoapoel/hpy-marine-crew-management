{{--
  Horizontal bar chart — one series, biggest first, value at each bar's tip.

  rows:  [['label' => ..., 'value' => int, 'tip' => optional extra line, 'href' => optional,
           'color' => optional hex for the whole bar, 'display' => optional text instead of the number,
           'segments' => optional [['name' => ..., 'value' => int, 'color' => hex], ...] to stack,
           'active' => optional bool, the row currently selected (a filter), drawn highlighted], ...]
  unit:  what the value counts, for tooltips and the table ("crew")
  empty: text for a zero value, shown as a flag instead of a bar (null = show 0)
  stacked: label and value on one line with the bar full-width beneath — for narrow cards

  Bars are ≤ 14px thick, square at the baseline and 4px-rounded at the data end. The
  whole row is the hover/focus target, not just the bar.
--}}
@props(['rows' => [], 'unit' => '', 'empty' => null, 'label' => 'Chart', 'stacked' => false])

@php
  $max = max(collect($rows)->max('value') ?? 0, 1);
@endphp

<div data-chart role="group" aria-label="{{ $label }}" {{ $attributes->class('space-y-0.5') }}>
  @foreach($rows as $row)
    @php
      $value = (int) $row['value'];
      $tag = !empty($row['href']) ? 'a' : 'div';
      $segments = array_values(array_filter($row['segments'] ?? [], fn ($seg) => $seg['value'] > 0));
      $breakdown = collect($segments)->map(fn ($seg) => $seg['name'] . ' ' . $seg['value'])->implode(' · ');
      $tipExtra = implode(' · ', array_filter([$breakdown, $row['tip'] ?? null]));
      $shown = $row['display'] ?? number_format($value);
    @endphp
    <{{ $tag }} @if($tag === 'a') href="{{ $row['href'] }}" @else tabindex="0" @endif
       @if(!empty($row['active'])) aria-current="true" @endif
       data-tip data-tip-title="{{ $row['label'] }}"
       data-tip-body="{{ isset($row['display']) ? $row['display'] : $value . ' ' . $unit }}{{ $tipExtra ? ' · ' . $tipExtra : '' }}"
       class="group block -mx-2 px-2 rounded-lg transition-colors hover:bg-slate-50 focus-visible:bg-slate-50 outline-none focus-visible:ring-2 focus-visible:ring-brand/40
              {{ !empty($row['active']) ? 'bg-brand/5 ring-2 ring-brand/30' : '' }}
              {{ $stacked ? 'py-1.5' : 'grid grid-cols-[minmax(0,8.5rem)_1fr_2.75rem] sm:grid-cols-[minmax(0,10rem)_1fr_2.75rem] items-center gap-3 py-[7px]' }}">
      @if($stacked)
        <span class="flex items-baseline justify-between gap-3 mb-1">
          <span class="text-[13px] text-slate-700 truncate group-hover:text-slate-900">{{ $row['label'] }}</span>
          <span class="text-[13px] font-semibold text-slate-900 tabular-nums whitespace-nowrap">{{ $shown }}</span>
        </span>
      @else
        <span class="text-[13px] text-slate-700 truncate group-hover:text-slate-900">{{ $row['label'] }}</span>
      @endif

      <span class="relative {{ $stacked ? 'h-2.5' : 'h-3.5' }} flex items-center">
        @if($value === 0 && $empty)
          <span class="chip border bg-rose-50 text-rose-700 border-rose-200">{{ $empty }}</span>
        @else
          <span class="absolute inset-y-0 left-0 right-0 rounded-r-[4px] bg-slate-100/70"></span>
          @php $width = max(round($value / $max * 100, 1), $value > 0 ? 1.5 : 0); @endphp
          @if(count($segments) > 1)
            {{-- Stacked: segments in fixed order, a 2px surface gap between them. --}}
            <span data-bar class="relative h-full origin-left flex gap-[2px] transition-[filter] group-hover:brightness-95" style="width: {{ $width }}%">
              @foreach($segments as $seg)
                <span class="h-full {{ $loop->last ? 'rounded-r-[4px]' : '' }}" style="flex: {{ $seg['value'] }} 1 0; background: {{ $seg['color'] }}"></span>
              @endforeach
            </span>
          @else
            <span data-bar class="relative h-full origin-left rounded-r-[4px] transition-[filter] group-hover:brightness-95"
                  style="width: {{ $width }}%; background: {{ $segments[0]['color'] ?? $row['color'] ?? 'var(--color-brand)' }}"></span>
          @endif
        @endif
      </span>

      @unless($stacked)
        <span class="text-right text-[13px] font-semibold text-slate-900 tabular-nums">{{ $shown }}</span>
      @endunless
    </{{ $tag }}>
  @endforeach
</div>

@php
  // The table spells the chart out: one column per stacked segment, the total, and
  // the tooltip's extra line as a column of its own.
  $segmentNames = collect($rows)->flatMap(fn ($r) => collect($r['segments'] ?? [])->pluck('name'))->unique()->values();
  $hasTip = collect($rows)->contains(fn ($r) => !empty($r['tip']));
  $hasDisplay = collect($rows)->contains(fn ($r) => isset($r['display']));
  $totalLabel = $unit !== '' ? ucfirst($unit) : 'Total';

  $tableColumns = collect([['label' => $label, 'align' => 'left']])
    ->concat($segmentNames->map(fn ($n) => ['label' => $n, 'align' => 'right']))
    ->push(['label' => $segmentNames->isNotEmpty() ? 'Total' : $totalLabel, 'align' => 'right'])
    ->when($hasTip, fn ($c) => $c->push(['label' => 'Note', 'align' => 'left']))
    ->all();

  $tableRows = collect($rows)->map(function ($r) use ($segmentNames, $hasTip) {
    $bySegment = collect($r['segments'] ?? [])->pluck('value', 'name');
    $cells = [$r['label']];
    foreach ($segmentNames as $n) {
      $cells[] = ($bySegment[$n] ?? 0) > 0 ? number_format($bySegment[$n]) : '—';
    }
    $cells[] = $r['display'] ?? number_format($r['value']);
    if ($hasTip) {
      $cells[] = $r['tip'] ?? '';
    }
    return $cells;
  })->all();

  $tableTotal = null;
  if (!$hasDisplay) {
    $tableTotal = ['Total'];
    foreach ($segmentNames as $n) {
      $tableTotal[] = number_format(collect($rows)->sum(fn ($r) => collect($r['segments'] ?? [])->firstWhere('name', $n)['value'] ?? 0));
    }
    $tableTotal[] = number_format(collect($rows)->sum('value'));
    if ($hasTip) {
      $tableTotal[] = '';
    }
  }
@endphp

<x-chart.table :columns="$tableColumns" :rows="$tableRows" :total="$tableTotal" :label="$label" />
