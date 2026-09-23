@extends('layouts.app')

@section('title', 'Dashboard · HPYMarine')
@section('heading', 'Dashboard')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ $user ? 'Hello, ' . $user : 'Dashboard' }}</h1>
      <p class="text-sm text-muted mt-1">{{ $today }}</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
      <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-green-400 opacity-60 animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-green-500"></span></span> Live data
    </div>
  </div>

  {{-- Stat cards, each one a way into the list it counts --}}
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach($stats as $s)
      <a href="{{ $s['href'] }}" class="block bg-panel border border-line rounded-xl p-5 shadow-sm hover:border-brand/60">
        <div class="flex items-center justify-between">
          <div class="text-xs text-muted">{{ $s['label'] }}</div>
          <span class="chip bg-brand/10 text-brand">{{ $s['delta'] }}</span>
        </div>
        <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-900" data-count>{{ $s['value'] }}</div>
        <div class="text-xs text-muted mt-1">{{ $s['sub'] }}</div>
      </a>
    @endforeach
  </div>

  @php
    $signOnTotal = collect($signOns)->sum('count');
    $thisMonth = end($signOns)['count'] ?? 0;
    $lastMonth = $signOns[count($signOns) - 2]['count'] ?? 0;
    $onboardTotal = collect($ranks)->sum('count');
    $busiest = collect($signOns)->sortByDesc('count')->first();
    $emptyShips = collect($fleet)->where('onboard', 0)->count();
    $colors = \App\Support\Departments::COLORS;
    $rankDepartments = collect($ranks)->pluck('department')->unique();
    $fleetDepartments = collect($fleet)->flatMap(fn ($v) => array_keys($v['departments']))->unique();
    $legend = fn ($present) => collect($colors)->only($present->all())->all();
    $fleetRows = collect($fleet)->take(12)->map(fn ($v) => [
      'label' => $v['vessel'],
      'value' => $v['onboard'],
      'segments' => collect($v['departments'])->map(fn ($n, $d) => ['name' => $d, 'value' => $n, 'color' => $colors[$d]])->values()->all(),
      'tip' => implode(' · ', array_filter([$v['type'] !== '—' ? $v['type'] : null, $v['planned'] ? '+' . $v['planned'] . ' joining' : null])),
      'href' => route('vessels.show', $v['name']),
    ])->all();
  @endphp

  {{-- Monthly sign on + onboard by rank --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="lg:col-span-2 bg-panel border border-line rounded-xl shadow-sm p-5">
      <div class="mb-4">
        <h3 class="text-slate-900 font-semibold">Monthly Crew Sign On</h3>
        <p class="text-xs text-muted">Crew who joined a vessel, per month</p>
      </div>

      <x-chart.area :points="collect($signOns)->map(fn ($m) => ['label' => $m['label'], 'full' => $m['full'], 'value' => $m['count']])->all()"
                    unit="crew signed on" label="Monthly crew sign on" />

      <dl class="mt-4 pt-4 border-t border-line grid grid-cols-3 gap-4">
        <div>
          <dt class="text-[11px] text-muted">Average / month</dt>
          <dd class="mt-0.5 text-lg font-semibold text-slate-900">{{ number_format($signOnTotal / max(count($signOns), 1), 1) }}</dd>
        </div>
        <div>
          <dt class="text-[11px] text-muted">Busiest month</dt>
          <dd class="mt-0.5 text-lg font-semibold text-slate-900">
            @if(($busiest['count'] ?? 0) > 0) {{ $busiest['full'] }} <span class="text-sm font-normal text-muted">· {{ $busiest['count'] }}</span> @else — @endif
          </dd>
        </div>
        <div>
          <dt class="text-[11px] text-muted">This month</dt>
          <dd class="mt-0.5 text-lg font-semibold text-slate-900">
            {{ $thisMonth }}
            @if($thisMonth !== $lastMonth)
              <span class="text-xs font-medium {{ $thisMonth > $lastMonth ? 'text-[#1e8e3e]' : 'text-[#d93025]' }}">{{ $thisMonth > $lastMonth ? '▲' : '▼' }} {{ abs($thisMonth - $lastMonth) }} vs last month</span>
            @endif
          </dd>
        </div>
      </dl>
    </section>

    <section class="bg-panel border border-line rounded-xl shadow-sm p-5">
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <h3 class="text-slate-900 font-semibold">Crew Onboard by Rank</h3>
          <p class="text-xs text-muted">Biggest first</p>
        </div>
        <div class="text-right">
          <div class="text-2xl font-semibold text-slate-900 leading-none" data-count>{{ number_format($onboardTotal) }}</div>
          <div class="text-[11px] text-muted mt-1">at sea</div>
        </div>
      </div>
      @if($ranks)
        <x-chart.legend :items="$legend($rankDepartments)" class="mb-3" />
        <x-chart.bars :rows="collect($ranks)->map(fn ($r) => ['label' => $r['name'], 'value' => $r['count'], 'color' => $colors[$r['department']], 'tip' => $r['department']])->all()"
                      unit="crew" label="Rank" stacked />
      @else
        <p class="text-sm text-muted py-8 text-center">Nobody is onboard yet.</p>
      @endif
    </section>
  </div>

  {{-- Onboard per vessel + this week's movements --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="lg:col-span-2 bg-panel border border-line rounded-xl shadow-sm p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
          <h3 class="text-slate-900 font-semibold">Crew Onboard per Vessel</h3>
          <p class="text-xs text-muted">
            {{ count($fleet) }} vessels
            @if($emptyShips) · <span class="text-rose-700 font-medium">{{ $emptyShips }} without crew</span>@endif
          </p>
        </div>
        <a href="{{ route('vessels.index') }}" class="text-xs text-brand hover:text-brand-d font-medium">Fleet →</a>
      </div>
      @if($fleetRows)
        <x-chart.legend :items="$legend($fleetDepartments)" class="mb-3" />
        <x-chart.bars :rows="$fleetRows" unit="crew onboard" empty="no crew" label="Vessel" />
        @if(count($fleet) > 12)
          <p class="mt-2 text-[11px] text-muted">+{{ count($fleet) - 12 }} more vessels — <a href="{{ route('vessels.index') }}" class="text-brand hover:underline">see the fleet</a></p>
        @endif
      @else
        <p class="text-sm text-muted py-8 text-center">No vessels in this company yet.</p>
      @endif
    </section>

    {{-- Movements due this week --}}
    <section class="bg-panel border border-line rounded-xl p-5 shadow-sm">
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
    </section>
  </div>

  {{-- Documents running out --}}
  <div>
    <div class="bg-panel border border-line rounded-xl shadow-sm">
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
            <div class="text-2xl font-semibold {{ $tones[$b['tone']] }}" data-count>{{ $b['count'] }}</div>
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
  </div>
@endsection
