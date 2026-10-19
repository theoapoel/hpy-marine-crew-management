{{--
  The table under a chart over time (area, columns): one row per period with the
  change against the period before, and a total. Included, not a component, so the
  two charts share it without another props contract.

  points: [['label' => 'Jan', 'full' => 'January 2026', 'value' => int], ...] oldest first
--}}
@php
  $previous = null;
  $periodRows = [];
  foreach ($points as $p) {
    $v = (int) $p['value'];
    $change = $previous === null ? '—' : match (true) {
      $v > $previous => '▲ ' . number_format($v - $previous),
      $v < $previous => '▼ ' . number_format($previous - $v),
      default => '=',
    };
    $periodRows[] = [$p['full'], number_format($v), $change];
    $previous = $v;
  }
@endphp

<x-chart.table
  :columns="[['label' => 'Month', 'align' => 'left'], ['label' => ucfirst($unit ?: 'Value'), 'align' => 'right'], ['label' => 'vs previous', 'align' => 'right']]"
  :rows="$periodRows"
  :total="['Total', number_format(collect($points)->sum('value')), '']"
  :label="$label" />
