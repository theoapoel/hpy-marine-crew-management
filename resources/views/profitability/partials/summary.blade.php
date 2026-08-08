{{-- Revenue / cost / margin, the three numbers every screen in this module opens with. --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
  @php
    $cards = [
      ['Revenue', $totals['revenue'], 'text-slate-900'],
      ['Cost', $totals['cost'], 'text-slate-900'],
      ['Margin', $totals['margin'], $totals['margin'] < 0 ? 'text-rose-600' : 'text-emerald-600'],
    ];
  @endphp
  @foreach($cards as [$label, $value, $tone])
    <div class="bg-panel border border-line rounded-xl px-5 py-4 shadow-sm">
      <div class="text-[11px] uppercase tracking-wider text-muted font-medium">{{ $label }}</div>
      <div class="mt-1 text-xl font-semibold {{ $tone }}">{{ number_format(round($value)) }}</div>
      @if($label === 'Margin')
        <div class="text-[11px] text-muted mt-0.5">
          {{ $totals['margin_pct'] === null ? 'no revenue yet' : $totals['margin_pct'] . '% of revenue' }}
        </div>
      @endif
    </div>
  @endforeach
</div>
