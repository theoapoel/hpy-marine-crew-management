@extends('layouts.app')

@section('title', 'Crew Master · HPYMarine')
@section('heading', 'Crew Master')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Crew Master</h1>
      <p class="text-sm text-muted mt-1">{{ $crews->total() }} crew members</p>
    </div>
    <a href="{{ route('crew.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add Crew</a>
  </div>

  {{-- Filters --}}
  <form method="GET" class="flex flex-wrap gap-2">
    <div class="relative">
      <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or seaman book..."
             class="bg-white border border-line rounded-md py-2 pl-8 pr-3 text-sm placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand w-72">
      <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    </div>
    <select name="status" class="bg-white border border-line rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
      <option value="">All statuses</option>
      @foreach($statuses as $s)
        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
      @endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
    @if(!empty($filters['search']) || !empty($filters['status']))
      <a href="{{ route('crew.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Reset</a>
    @endif
  </form>

  {{-- Table --}}
  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Name</th>
            <th class="text-left px-3 py-3 font-medium">Rank</th>
            <th class="text-left px-3 py-3 font-medium">Vessel</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-left px-3 py-3 font-medium">Contract End</th>
            <th class="text-right px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($crews as $crew)
            @php
              $colors = [
                'Onboard'  => 'bg-green-50 text-green-700 border-green-200',
                'Standby'  => 'bg-blue-50 text-blue-700 border-blue-200',
                'Sign Off' => 'bg-slate-100 text-slate-600 border-slate-200',
              ];
            @endphp
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  @if($crew->image)
                    <img src="{{ route('erp.file', ['path' => $crew->image]) }}" alt="{{ $crew->name }}"
                         class="w-8 h-8 rounded-full object-cover border border-line shrink-0">
                  @else
                    <div class="w-8 h-8 rounded-full bg-slate-100 text-muted flex items-center justify-center text-[11px] font-semibold shrink-0">
                      {{ mb_strtoupper(mb_substr($crew->name, 0, 1)) }}
                    </div>
                  @endif
                  <div>
                    <a href="{{ route('crew.show', $crew) }}" class="text-slate-900 font-medium hover:text-brand">{{ $crew->name }}</a>
                    <div class="text-[11px] text-muted">{{ $crew->seaman_book_no ?: '—' }} · {{ $crew->nationality }}</div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $crew->rank }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $crew->vessel ?: '—' }}</td>
              <td class="px-3 py-3"><span class="chip border {{ $colors[$crew->status] ?? '' }}">{{ $crew->status }}</span></td>
              <td class="px-3 py-3 text-slate-600">{{ $crew->contract_end_date?->format('d M Y') ?? '—' }}</td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a href="{{ route('crew.edit', $crew) }}" class="text-brand hover:text-brand-d font-medium">Edit</a>
                <form action="{{ route('crew.destroy', $crew) }}" method="POST" class="inline" onsubmit="return confirm('Remove {{ $crew->name }}?')">
                  @csrf @method('DELETE')
                  <button class="text-rose-500 hover:text-rose-600 ml-3">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-muted">No crew found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $crews->links() }}</div>
@endsection
