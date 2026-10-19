{{--
  Column chart over categories — one column per item, biggest first, grown from a
  shared baseline. Built for a narrow card: every cap carries its value and the
  labels run upwards under their column, so long names ("Chief Engineer") still fit.

  rows:  [['label' => 'Master', 'value' => int, 'color' => optional hex, 'tip' => optional extra line], ...]
  unit:  what the value counts, for tooltips and the table ("crew")
  note:  heading for the tip column in the table ("Department")
--}}
@props(['rows' => [], 'unit' => '', 'label' => 'Chart', 'note' => 'Note'])

@php
  $values = array_map(fn ($r) => (int) $r['value'], $rows);
  $peak = max($values ?: [0]);

  // Four even steps, each a clean 1 / 2 / 5 × 10ⁿ, so the ticks read 0 · 5 · 10 · 15 · 20.
  $raw = max($peak, 4) / 4;
  $magnitude = 10 ** floor(log10($raw));
  $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $raw);
  $step = max(1, (int) ceil($step));
  $top = $step * 4;
  $ticks = [0, $step, $step * 2, $step * 3, $top];

  $hasTip = collect($rows)->contains(fn ($r) => !empty($r['tip']));
@endphp

<div data-chart role="group" aria-label="{{ $label }}" {{ $attributes->class('relative') }}>
  <div class="relative h-48 ml-8">
    {{-- Gridlines + y ticks --}}
    @foreach($ticks as $tick)
      <div class="absolute inset-x-0 border-t {{ $tick == 0 ? 'border-slate-300' : 'border-line' }}" style="bottom: {{ $tick / $top * 100 }}%">
        <span class="absolute -left-8 w-6 -translate-y-1/2 text-right text-[11px] text-muted tabular-nums">{{ number_format($tick) }}</span>
      </div>
    @endforeach

    {{-- Columns: the whole slot is the hover/focus target --}}
    <div class="absolute inset-0 flex">
      @foreach($rows as $row)
        @php $v = (int) $row['value']; @endphp
        <div tabindex="0" data-tip data-tip-title="{{ $row['label'] }}"
             data-tip-body="{{ $v }} {{ $unit }}{{ !empty($row['tip']) ? ' · ' . $row['tip'] : '' }}"
             class="group relative flex-1 min-w-0 h-full flex items-end justify-center px-[3px] rounded-md outline-none transition-colors hover:bg-slate-100/70 focus-visible:bg-slate-100/70 focus-visible:ring-2 focus-visible:ring-brand/40">
          <div class="relative w-full max-w-7 flex justify-center" style="height: {{ $v / $top * 100 }}%">
            @if($v > 0)
              <div data-col class="w-full h-full origin-bottom rounded-t-[4px] transition-[filter] group-hover:brightness-95"
                   style="background: {{ $row['color'] ?? 'var(--color-brand)' }}"></div>
            @endif
            <span class="absolute -top-5 text-[11px] font-semibold text-slate-900 tabular-nums">{{ number_format($v) }}</span>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Category labels, reading upwards under their column --}}
  <div class="ml-8 mt-2 flex h-24">
    @foreach($rows as $row)
      <div class="flex-1 min-w-0 flex justify-center">
        <span class="[writing-mode:vertical-rl] rotate-180 text-[11px] leading-none text-slate-600 truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
      </div>
    @endforeach
  </div>
</div>

@php
  $tableColumns = collect([['label' => $label, 'align' => 'left']])
    ->when($hasTip, fn ($c) => $c->push(['label' => $note, 'align' => 'left']))
    ->push(['label' => $unit !== '' ? ucfirst($unit) : 'Total', 'align' => 'right'])
    ->all();

  $tableRows = collect($rows)->map(fn ($r) => array_values(array_filter([
    $r['label'],
    $hasTip ? ($r['tip'] ?? '') : null,
    number_format($r['value']),
  ], fn ($c) => $c !== null)))->all();

  $tableTotal = array_values(array_filter(['Total', $hasTip ? '' : null, number_format(array_sum($values))], fn ($c) => $c !== null));
@endphp

<x-chart.table :columns="$tableColumns" :rows="$tableRows" :total="$tableTotal" :label="$label" />
