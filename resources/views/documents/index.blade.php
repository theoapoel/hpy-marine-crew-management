@extends('layouts.app')

@section('title', $title . ' · HPYMarine')
@section('heading', 'Documents')

@section('content')
  @php
    $file = fn ($path) => route('erp.file', ['path' => $path]);
    $fmt = fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '—';
    $colors = [
      'Valid' => 'bg-green-50 text-green-700 border-green-200',
      'Expiring Soon' => 'bg-amber-50 text-amber-700 border-amber-200',
      'Expired' => 'bg-rose-50 text-rose-700 border-rose-200',
      'Revoked' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
      <p class="text-sm text-muted mt-1">
        {{ $documents->count() }} dokumen
        @if($view === 'expiring') · kadaluarsa dalam {{ $filters['expiring_in'] ?? 60 }} hari @endif
      </p>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama crew atau nomor..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand w-72">

    @if($view === 'certificates')
      <select name="type" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
        <option value="">Semua jenis</option>
        @foreach($types as $type)
          <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
        @endforeach
      </select>
    @endif

    <select name="status" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua status</option>
      @foreach($statuses as $status)
        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
      @endforeach
    </select>

    @if($view === 'expiring')
      <select name="days" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
        @foreach([30, 60, 90, 180] as $days)
          <option value="{{ $days }}" @selected((int) ($filters['expiring_in'] ?? 60) === $days)>{{ $days }} hari</option>
        @endforeach
      </select>
    @endif

    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Crew</th>
            <th class="text-left px-3 py-3 font-medium">Jenis</th>
            <th class="text-left px-3 py-3 font-medium">Nomor</th>
            <th class="text-left px-3 py-3 font-medium">Terbit</th>
            <th class="text-left px-3 py-3 font-medium">Kadaluarsa</th>
            <th class="text-left px-3 py-3 font-medium">Sisa</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-right px-5 py-3 font-medium">File</th>
          </tr>
        </thead>
        <tbody>
          @forelse($documents as $doc)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3">
                <a href="{{ route('crew.show', $doc->crew_id) }}" class="text-slate-900 font-medium hover:text-brand">{{ $doc->crew_name }}</a>
                <div class="text-[11px] text-muted">{{ $doc->rank ?: '—' }}{{ $doc->vessel ? ' · ' . $doc->vessel : '' }}</div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $doc->certificate_type }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $doc->certificate_number ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($doc->issue_date) }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($doc->expiry_date) }}</td>
              <td class="px-3 py-3 text-slate-600">
                {{ $doc->days_left === null ? '—' : $doc->days_left . ' hari' }}
              </td>
              <td class="px-3 py-3"><span class="chip border {{ $colors[$doc->status] ?? '' }}">{{ $doc->status }}</span></td>
              <td class="px-5 py-3 text-right">
                @if($doc->attachment)
                  <button type="button" data-preview="{{ $file($doc->attachment) }}"
                          data-preview-title="{{ $doc->certificate_type }} — {{ $doc->crew_name ?? basename($doc->attachment) }}"
                          class="text-brand hover:text-brand-d font-medium">Preview</button>
                  <span class="text-line mx-1">|</span>
                  <a href="{{ $file($doc->attachment) }}" download class="text-slate-600 hover:text-slate-900">Download</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">Tidak ada dokumen.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
