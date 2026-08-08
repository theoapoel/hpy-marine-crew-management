{{-- The point of the module: where the money came from, and where it went. --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  @foreach([['Revenue', $components['revenue'], 'emerald'], ['Cost', $components['cost'], 'rose']] as [$heading, $rows, $tone])
    @php $largest = max(collect($rows)->max(fn ($r) => abs($r['amount'])) ?? 0, 1); @endphp

    <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-line flex items-baseline justify-between">
        <h2 class="text-sm font-semibold text-slate-900">{{ $heading }} by component</h2>
        <span class="text-[11px] text-muted">{{ count($rows) }} components</span>
      </div>
      <div class="divide-y divide-line">
        @forelse($rows as $row)
          <div class="px-5 py-3">
            <div class="flex items-baseline justify-between text-sm">
              <span class="text-slate-700">{{ $row['component'] }}</span>
              <span class="font-medium text-slate-900 tabular-nums">{{ number_format(round($row['amount'])) }}</span>
            </div>
            <div class="mt-1.5 h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-{{ $tone }}-500 rounded-full" style="width: {{ round(abs($row['amount']) / $largest * 100) }}%"></div>
            </div>
            <div class="text-[11px] text-muted mt-1">{{ $row['documents'] }} document{{ $row['documents'] === 1 ? '' : 's' }}</div>
          </div>
        @empty
          <div class="px-5 py-8 text-center text-muted text-sm">Nothing posted yet.</div>
        @endforelse
      </div>
    </div>
  @endforeach
</div>
