{{--
  Area chart over time, Google Finance style: one line with a soft gradient wash,
  coloured by the trend across the selected range (green up / red down, always with
  ▲/▼ text), range buttons, and a crosshair tooltip. Drawn by ui.js (areaCharts).

  points: [['label' => 'Jan', 'full' => 'January 2026', 'value' => int], ...] oldest first
  ranges: month counts offered as buttons; the last one is selected first
  unit:   what the value counts ("crew signed on")
--}}
@props(['points' => [], 'ranges' => [3, 6, 12], 'unit' => '', 'label' => 'Chart'])

<div data-area-chart data-unit="{{ $unit }}" data-points='@json(array_values($points))' {{ $attributes }}>
  <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
      <div class="flex items-baseline gap-2">
        <span data-area-total class="text-3xl font-semibold text-slate-900 leading-none">{{ number_format(collect($points)->sum('value')) }}</span>
        <span class="text-sm text-muted">{{ $unit }}</span>
      </div>
      <div data-area-delta class="mt-1.5 text-xs font-medium"></div>
    </div>

    <div role="group" aria-label="Range" class="inline-flex rounded-full border border-line p-0.5 bg-slate-50">
      @foreach($ranges as $months)
        <button type="button" data-area-range="{{ $months }}" aria-pressed="{{ $loop->last ? 'true' : 'false' }}"
                class="px-3 py-1 rounded-full text-xs font-medium text-slate-600 transition-colors hover:text-slate-900 aria-pressed:bg-white aria-pressed:text-brand aria-pressed:shadow-sm">
          {{ $months === 12 ? '1Y' : $months . 'M' }}
        </button>
      @endforeach
    </div>
  </div>

  <div data-area-plot role="img" aria-label="{{ $label }}" tabindex="0"
       class="relative h-60 lg:h-80 -mx-1 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-brand/40"></div>
</div>

<details class="mt-3 group/t">
  <summary class="inline-flex items-center gap-1 text-[11px] text-muted hover:text-slate-700 select-none">
    <span class="transition-transform group-open/t:rotate-90">›</span> View as table
  </summary>
  <table class="mt-2 w-full text-xs">
    <thead><tr class="text-muted border-b border-line"><th class="text-left py-1.5 font-medium">Month</th><th class="text-right py-1.5 font-medium capitalize">{{ $unit }}</th></tr></thead>
    <tbody>
      @foreach($points as $p)
        <tr class="border-b border-line last:border-0"><td class="py-1.5 text-slate-700">{{ $p['full'] }}</td><td class="py-1.5 text-right text-slate-900">{{ number_format($p['value']) }}</td></tr>
      @endforeach
    </tbody>
  </table>
</details>
