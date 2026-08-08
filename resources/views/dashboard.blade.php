@extends('layouts.app')

@section('title', 'Dashboard · HPYMarine')
@section('heading', 'Dashboard')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ $user ? 'Halo, ' . $user : 'Dashboard' }}</h1>
      <p class="text-sm text-muted mt-1">{{ $today }}</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
      <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Live data
    </div>
  </div>

  {{-- Stat cards, each one a way into the list it counts --}}
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach($stats as $s)
      <a href="{{ $s['href'] }}" class="block bg-panel border border-line rounded-xl p-5 shadow-sm hover:border-brand transition">
        <div class="flex items-center justify-between">
          <div class="text-xs text-muted">{{ $s['label'] }}</div>
          <span class="chip bg-brand/10 text-brand">{{ $s['delta'] }}</span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $s['value'] }}</div>
        <div class="text-xs text-muted mt-1">{{ $s['sub'] }}</div>
      </a>
    @endforeach
  </div>

  {{-- Documents running out + ranks aboard --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-panel border border-line rounded-xl shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-line">
        <div>
          <h3 class="text-slate-900 font-semibold">Documents Running Out</h3>
          <p class="text-xs text-muted">{{ $documents['total'] }} within 90 days</p>
        </div>
        <a href="{{ route('documents', 'expiring') }}" class="text-xs text-brand hover:text-brand-d font-medium">All documents →</a>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-line border-b border-line">
        @foreach($documents['buckets'] as $b)
          @php
            $tones = [
              'rose'  => 'text-rose-600',
              'amber' => 'text-amber-600',
              'sky'   => 'text-sky-600',
              'slate' => 'text-slate-500',
            ];
          @endphp
          <div class="px-5 py-4">
            <div class="text-2xl font-semibold {{ $tones[$b['tone']] }}">{{ $b['count'] }}</div>
            <div class="text-[11px] text-muted mt-0.5">{{ $b['label'] }}</div>
          </div>
        @endforeach
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <tbody>
            @forelse($documents['soonest'] as $d)
              <tr class="border-b border-line last:border-0 hover:bg-slate-50">
                <td class="px-5 py-3">
                  <div class="text-slate-900 font-medium">{{ $d->crew_name }}</div>
                  <div class="text-[11px] text-muted">{{ $d->rank ?: '—' }}</div>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $d->certificate_type }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $d->expiry_date }}</td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  @if($d->days_left < 0)
                    <span class="chip border bg-rose-50 text-rose-700 border-rose-200">{{ abs($d->days_left) }}d overdue</span>
                  @else
                    <span class="chip border bg-amber-50 text-amber-700 border-amber-200">{{ $d->days_left }}d left</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td class="px-5 py-8 text-center text-muted">Nothing expiring in the next 90 days.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Ranks aboard --}}
    <div class="bg-panel border border-line rounded-xl p-5 shadow-sm">
      <h3 class="text-slate-900 font-semibold">Ranks Onboard</h3>
      <p class="text-xs text-muted">{{ collect($ranks)->sum('count') }} crew at sea</p>
      <div class="mt-5 space-y-3">
        @forelse($ranks as $r)
          <div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-700">{{ $r['name'] }}</span>
              <span class="text-muted">{{ $r['count'] }}</span>
            </div>
            <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-brand to-brand-d" style="width: {{ $r['pct'] }}%"></div>
            </div>
          </div>
        @empty
          <p class="text-sm text-muted">Nobody is onboard yet.</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Manning + this week's movements --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-panel border border-line rounded-xl shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-line">
        <div>
          <h3 class="text-slate-900 font-semibold">Manning per Vessel</h3>
          <p class="text-xs text-muted">Emptiest ships first</p>
        </div>
        <a href="{{ route('vessels.index') }}" class="text-xs text-brand hover:text-brand-d font-medium">Fleet →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50">
              <th class="text-left px-5 py-2 font-medium">Vessel</th>
              <th class="text-left px-3 py-2 font-medium">Principal</th>
              <th class="text-right px-3 py-2 font-medium">Onboard</th>
              <th class="text-right px-5 py-2 font-medium">Planned</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fleet as $v)
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-5 py-3">
                  <a href="{{ route('vessels.show', $v['name']) }}" class="text-slate-900 font-medium hover:text-brand">{{ $v['vessel'] }}</a>
                  <div class="text-[11px] text-muted">{{ $v['type'] }}</div>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $v['principal'] }}</td>
                <td class="px-3 py-3 text-right">
                  @if($v['onboard'] === 0)
                    <span class="chip border bg-rose-50 text-rose-700 border-rose-200">no crew</span>
                  @else
                    <span class="text-slate-900 font-medium">{{ $v['onboard'] }}</span>
                  @endif
                </td>
                <td class="px-5 py-3 text-right text-slate-600">{{ $v['planned'] ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-5 py-8 text-center text-muted">No vessels in this company yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Movements due this week --}}
    <div class="bg-panel border border-line rounded-xl p-5 shadow-sm">
      <h3 class="text-slate-900 font-semibold">Due This Week</h3>
      <p class="text-xs text-muted">Up to {{ $movements['until']->translatedFormat('j F') }}</p>

      @foreach([['joining', 'Sign On', 'assignments.sign-on', 'planned_sign_on_date', 'bg-brand'], ['leaving', 'Sign Off', 'assignments.sign-off', 'planned_sign_off_date', 'bg-slate-400']] as [$key, $title, $route, $date, $dot])
        <div class="mt-5">
          <div class="flex items-center justify-between">
            <div class="text-xs font-medium text-slate-700">{{ $title }} ({{ $movements[$key]->count() }})</div>
            <a href="{{ route($route) }}" class="text-[11px] text-brand hover:text-brand-d">Open desk →</a>
          </div>
          <ul class="mt-2 space-y-3">
            @forelse($movements[$key]->take(5) as $a)
              <li class="flex gap-3">
                <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $a->$date->isPast() ? 'bg-rose-500' : $dot }}"></span>
                <div class="flex-1 min-w-0">
                  <div class="text-[13px] text-slate-700 leading-snug truncate">{{ $a->crew_name }}</div>
                  <div class="text-[11px] text-muted truncate">{{ $a->rank ?: '—' }} · {{ $a->vessel ?: 'no vessel' }}</div>
                  <div class="text-[10px] {{ $a->$date->isPast() ? 'text-rose-600' : 'text-muted' }} mt-0.5">
                    {{ $a->$date->translatedFormat('j M') }}{{ $a->$date->isPast() ? ' · overdue' : '' }}
                  </div>
                </div>
              </li>
            @empty
              <li class="text-[13px] text-muted">Nothing due.</li>
            @endforelse
          </ul>
        </div>
      @endforeach
    </div>
  </div>
@endsection
