@extends('layouts.app')

@section('title', 'Crew Assignment · HPYMarine')
@section('heading', 'Crew Assignment')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $colors = [
      'planned'    => 'bg-blue-50 text-blue-700 border-blue-200',
      'onboard'    => 'bg-green-50 text-green-700 border-green-200',
      'signed_off' => 'bg-slate-100 text-slate-600 border-slate-200',
      'cancelled'  => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Crew Assignment</h1>
      <p class="text-sm text-muted mt-1">{{ $assignments->total() }} penugasan</p>
    </div>
    @can('assignments.create')
      <a href="{{ route('assignments.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Penugasan Baru</a>
    @endcan
  </div>

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama crew atau kode..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="status" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua status</option>
      @foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $text($status) }}</option>@endforeach
    </select>
    <select name="vessel" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua kapal</option>
      @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected(($filters['vessel'] ?? '') === $vessel)>{{ $vessel }}</option>@endforeach
    </select>
    <select name="rank" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua rank</option>
      @foreach($ranks as $rank)<option value="{{ $rank }}" @selected(($filters['rank'] ?? '') === $rank)>{{ $rank }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Kode</th>
            <th class="text-left px-3 py-3 font-medium">Crew</th>
            <th class="text-left px-3 py-3 font-medium">Kapal</th>
            <th class="text-left px-3 py-3 font-medium">Rank</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-left px-3 py-3 font-medium">Sign On</th>
            <th class="text-left px-3 py-3 font-medium">Sign Off</th>
            <th class="text-right px-5 py-3 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($assignments as $assignment)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3 font-mono text-[12px] text-muted">{{ $assignment->assignment_code }}</td>
              <td class="px-3 py-3">
                <a href="{{ route('assignments.show', $assignment) }}" class="text-slate-900 font-medium hover:text-brand">{{ $assignment->crew_name }}</a>
                <div class="text-[11px] text-muted">{{ $assignment->employee_id ?: 'belum jadi employee' }}</div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->vessel ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->rank ?: '—' }}</td>
              <td class="px-3 py-3">
                <span class="chip border {{ $colors[$assignment->status] ?? '' }}">{{ $text($assignment->status) }}</span>
                @if($assignment->is_overdue)<span class="chip border bg-rose-50 text-rose-700 border-rose-200 ml-1">lewat kontrak</span>@endif
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->sign_on_date?->format('d M Y') ?? ($assignment->planned_sign_on_date ? 'rencana ' . $assignment->planned_sign_on_date->format('d M Y') : '—') }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $assignment->sign_off_date?->format('d M Y') ?? ($assignment->planned_sign_off_date ? 'rencana ' . $assignment->planned_sign_off_date->format('d M Y') : '—') }}</td>
              <td class="px-5 py-3 text-right">
                <a href="{{ route('assignments.show', $assignment) }}" class="text-brand hover:text-brand-d font-medium">Buka</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">Belum ada penugasan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $assignments->links() }}</div>
@endsection
