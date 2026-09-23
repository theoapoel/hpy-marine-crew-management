{{--
  Column chart over time — one series, one bar per period, grown from a shared baseline.

  points: [['label' => 'Jan', 'full' => 'January 2026', 'value' => int], ...] oldest first
  unit:   what the value counts ("crew signed on")

  Clean y ticks (0 … a round maximum) on hairline gridlines; only the peak and the
  latest period are labelled on their cap — the tooltip and table carry the rest.
--}}
@props(['points' => [], 'unit' => '', 'label' => 'Chart'])

@php
  $values = array_map(fn ($p) => (int) $p['value'], $points);
  $peak = max($values ?: [0]);

  // Four even steps, each a clean 1 / 2 / 5 × 10ⁿ, so the ticks read 0 · 5 · 10 · 15 · 20.
  $raw = max($peak, 4) / 4;
  $magnitude = 10 ** floor(log10($raw));
  $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $raw);
  $step = max(1, (int) ceil($step));
  $top = $step * 4;
  $ticks = [0, $step, $step * 2, $step * 3, $top];

  $peakIndex = $peak > 0 ? array_search($peak, $values, true) : null;
  $lastIndex = count($points) - 1;
@endphp

<div data-chart role="group" aria-label="{{ $label }}" {{ $attributes->class('relative') }}>
  <div class="relative h-56 lg:h-64 ml-8">
    {{-- Gridlines + y ticks --}}
    @foreach($ticks as $tick)
      <div class="absolute inset-x-0 border-t {{ $tick == 0 ? 'border-slate-300' : 'border-line' }}" style="bottom: {{ $tick / $top * 100 }}%">
        <span class="absolute -left-8 w-6 -translate-y-1/2 text-right text-[11px] text-muted tabular-nums">{{ number_format($tick) }}</span>
      </div>
    @endforeach

    {{-- Columns: the whole slot is the hover/focus target --}}
    <div class="absolute inset-0 flex">
      @foreach($points as $i => $p)
        @php $v = (int) $p['value']; $labelled = $i === $peakIndex || ($i === $lastIndex && $v > 0); @endphp
        <div tabindex="0" data-tip data-tip-title="{{ $p['full'] }}" data-tip-body="{{ $v }} {{ $unit }}"
             class="group relative flex-1 h-full flex items-end justify-center px-[3px] rounded-md outline-none transition-colors hover:bg-slate-100/70 focus-visible:bg-slate-100/70 focus-visible:ring-2 focus-visible:ring-brand/40">
          <div class="relative w-full max-w-6 flex justify-center" style="height: {{ $v / $top * 100 }}%">
            @if($v > 0)
              <div data-col class="w-full h-full origin-bottom rounded-t-[4px] bg-brand transition-colors group-hover:bg-brand-d"></div>
            @endif
            @if($labelled)
              <span class="absolute -top-5 text-[11px] font-semibold text-slate-900 tabular-nums">{{ number_format($v) }}</span>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Period labels, aligned to the slots above --}}
  <div class="ml-8 mt-2 flex">
    @foreach($points as $i => $p)
      <div class="flex-1 text-center text-[11px] {{ $i === $lastIndex ? 'text-slate-900 font-medium' : 'text-muted' }}">{{ $p['label'] }}</div>
    @endforeach
  </div>
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
