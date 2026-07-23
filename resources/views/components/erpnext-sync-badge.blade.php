@props(['model'])

@php
  $status = $model->erpnext_sync_status ?? 'pending';
  $colors = [
    'synced' => 'bg-green-50 text-green-700 border-green-200',
    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
    'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
  ];
  $title = match ($status) {
    'synced' => 'ERP HPY: ' . ($model->erpnext_name ?? '') . ' · ' . ($model->erpnext_synced_at?->diffForHumans() ?? ''),
    'failed' => 'ERP HPY: ' . ($model->erpnext_sync_error ?? 'sync failed'),
    default => 'Belum dikirim ke ERP HPY',
  };
@endphp

<span class="chip border {{ $colors[$status] ?? $colors['pending'] }}" title="{{ $title }}">
  {{ ucfirst($status) }}
</span>
