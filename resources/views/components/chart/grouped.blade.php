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

<details class="mt-2 group/t">
  <summary class="inline-flex items-center gap-1 text-[11px] text-muted hover:text-slate-700 select-none">
    <span class="transition-transform group-open/t:rotate-90">›</span> View as table
  </summary>
  <div class="overflow-x-auto">
    <table class="mt-2 w-full text-xs">
      <thead>
        <tr class="text-muted border-b border-line">
          <th class="text-left py-1.5 font-medium">Period</th>
          @foreach($series as $s)<th class="text-right py-1.5 pl-3 font-medium">{{ $s['name'] }}</th>@endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($labels as $i => $l)
          <tr class="border-b border-line last:border-0">
            <td class="py-1.5 text-slate-700 whitespace-nowrap">{{ $l }}</td>
            @foreach($series as $s)<td class="py-1.5 pl-3 text-right tabular-nums text-slate-900 whitespace-nowrap">{{ $money($s['values'][$i] ?? 0) }}</td>@endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</details>
