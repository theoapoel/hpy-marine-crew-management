{{--
  "View as table" under a chart: the same numbers as a proper table, folded away
  until asked for. Every chart component uses this one, so the tables read alike.

  columns: [['label' => 'Vessel', 'align' => 'left'|'right'], ...]
  rows:    [[cell, cell, ...], ...] already formatted text, one per column
  total:   optional footer row, same shape as a row
  label:   accessible name for the table
  empty:   text for no rows at all
--}}
@props(['columns' => [], 'rows' => [], 'total' => null, 'label' => 'Table', 'empty' => 'No data.'])

@php
  $align = fn ($c) => ($c['align'] ?? 'left') === 'right' ? 'text-right' : 'text-left';
@endphp

<details class="mt-3 group/t">
  <summary class="inline-flex items-center gap-1 text-[11px] text-muted hover:text-slate-700 select-none cursor-pointer list-none [&::-webkit-details-marker]:hidden">
    <i data-lucide="chevron-right" class="size-3 transition-transform group-open/t:rotate-90"></i>
    <span class="group-open/t:hidden">View as table</span><span class="hidden group-open/t:inline">Hide table</span>
  </summary>

  <div class="mt-2 overflow-x-auto rounded-lg border border-line">
    <table class="w-full text-xs" aria-label="{{ $label }}">
      <thead>
        <tr class="text-[11px] uppercase tracking-wider text-muted bg-slate-50 border-b border-line">
          @foreach($columns as $c)
            <th scope="col" class="px-3 py-2 font-medium whitespace-nowrap {{ $align($c) }}">{{ $c['label'] }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr class="border-b border-line last:border-0 hover:bg-slate-50">
            @foreach($row as $i => $cell)
              <td class="px-3 py-1.5 whitespace-nowrap {{ $align($columns[$i] ?? []) }} {{ $i === 0 ? 'text-slate-700' : 'text-slate-900 tabular-nums' }}">{{ $cell }}</td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="{{ max(count($columns), 1) }}" class="px-3 py-4 text-center text-muted">{{ $empty }}</td></tr>
        @endforelse
      </tbody>
      @if($total && $rows)
        <tfoot>
          <tr class="bg-slate-50 border-t border-line font-semibold text-slate-900">
            @foreach($total as $i => $cell)
              <td class="px-3 py-2 whitespace-nowrap tabular-nums {{ $align($columns[$i] ?? []) }}">{{ $cell }}</td>
            @endforeach
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</details>
