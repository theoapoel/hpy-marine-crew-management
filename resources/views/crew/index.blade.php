@extends('layouts.app')

@section('title', 'Crew Master · HPYMarine')
@section('heading', 'Crew Master')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Crew Master</h1>
      <p class="text-sm text-muted mt-1">
        {{ $crews->total() }} crew members
        @if(!empty($filters['rank']))
          · rank <span class="font-medium text-slate-700">{{ $filters['rank'] }}</span>
        @endif
      </p>
    </div>
    <a href="{{ route('crew.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add Crew</a>
  </div>

  {{-- Rank cards: how many crew hold each rank, and where they are. A card filters the list. --}}
  @if($rankCards)
    @php
      $activeRank = $filters['rank'] ?? null;
      $allTotal = collect($rankCards)->sum('total');
      $statusColors = ['onboard' => '#1e8e3e', 'standby' => '#f29900', 'signed_off' => '#cbd5e1'];
      $departments = collect($rankCards)->pluck('color', 'department');
      $link = fn (?string $rank) => route('crew.index', array_filter(['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null, 'rank' => $rank]));
    @endphp
    <section aria-label="Crew by rank">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 class="text-sm font-semibold text-slate-900">By Rank</h2>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-600">
          @foreach($departments as $name => $color)
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-[3px]" style="background: {{ $color }}"></span>{{ $name }}</span>
          @endforeach
          <span class="hidden sm:block w-px h-3 bg-line"></span>
          <span class="flex items-center gap-1.5"><span class="h-1.5 w-3 rounded-full" style="background: {{ $statusColors['onboard'] }}"></span>Onboard</span>
          <span class="flex items-center gap-1.5"><span class="h-1.5 w-3 rounded-full" style="background: {{ $statusColors['standby'] }}"></span>Standby</span>
          <span class="flex items-center gap-1.5"><span class="h-1.5 w-3 rounded-full" style="background: {{ $statusColors['signed_off'] }}"></span>Sign Off</span>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6 gap-3">
        <a href="{{ $link(null) }}" @if(!$activeRank) aria-current="true" @endif
           class="bg-panel relative overflow-hidden rounded-xl border p-4 shadow-sm {{ $activeRank ? 'border-line hover:border-slate-300' : 'border-brand ring-2 ring-brand/20' }}">
          <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#1a73e8] via-[#e8710a] to-[#9334e6]"></span>
          <div class="text-[11px] text-muted">All ranks</div>
          <div class="mt-1 text-[13px] font-medium text-slate-900">Every crew</div>
          <div class="mt-2 text-2xl font-semibold text-slate-900" data-count>{{ number_format($allTotal) }}</div>
          <div class="mt-1 text-[11px] text-muted">{{ count($rankCards) }} ranks</div>
        </a>

        @foreach($rankCards as $card)
          @php $active = $activeRank === $card['rank']; @endphp
          <a href="{{ $link($active ? null : $card['rank']) }}" @if($active) aria-current="true" @endif
             title="{{ $active ? 'Show all ranks' : 'Show only ' . $card['rank'] }}"
             class="bg-panel group relative overflow-hidden rounded-xl border p-4 shadow-sm {{ $active ? 'ring-2' : 'border-line hover:border-slate-300' }}"
             @if($active) style="border-color: {{ $card['color'] }}; --tw-ring-color: {{ $card['color'] }}33" @endif>
            <span class="absolute inset-x-0 top-0 h-1" style="background: {{ $card['color'] }}"></span>
            <div class="flex items-center gap-1.5 text-[11px] text-muted">
              <span class="size-1.5 rounded-full" style="background: {{ $card['color'] }}"></span>{{ $card['department'] }}
            </div>
            <div class="mt-1 text-[13px] font-medium text-slate-900 truncate" title="{{ $card['rank'] }}">{{ $card['rank'] }}</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900" data-count>{{ number_format($card['total']) }}</div>

            {{-- Where they are: onboard / standby / signed off, with a 2px gap between. --}}
            <div class="mt-2 flex h-1.5 gap-[2px] rounded-full overflow-hidden bg-slate-100">
              @foreach(['onboard', 'standby', 'signed_off'] as $key)
                @if($card[$key] > 0)
                  <span data-bar class="h-full origin-left" style="flex: {{ $card[$key] }} 1 0; background: {{ $statusColors[$key] }}"></span>
                @endif
              @endforeach
            </div>
            <div class="mt-1.5 text-[11px] text-muted truncate">
              {{ $card['onboard'] }} onboard · {{ $card['standby'] }} standby
            </div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

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
    @if(!empty($filters['rank']))
      <input type="hidden" name="rank" value="{{ $filters['rank'] }}">
    @endif
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
    @if(!empty($filters['search']) || !empty($filters['status']) || !empty($filters['rank']))
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
