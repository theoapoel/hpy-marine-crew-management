@extends('layouts.app')

@section('title', 'Dashboard · HPYMarine')
@section('heading', 'Dashboard')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Good morning, Admin</h1>
      <p class="text-sm text-muted mt-1">{{ $today }} · Fleet overview</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
      <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Live data
    </div>
  </div>

  {{-- Stat cards --}}
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach($stats as $s)
      <div class="bg-panel border border-line rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div class="text-xs text-muted">{{ $s['label'] }}</div>
          <span class="chip bg-brand/10 text-brand">{{ $s['delta'] }}</span>
        </div>
        <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $s['value'] }}</div>
        <div class="text-xs text-muted mt-1">{{ $s['sub'] }}</div>
      </div>
    @endforeach
  </div>

  {{-- Charts row --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Revenue chart (illustrative placeholder — no finance module yet) --}}
    <div class="lg:col-span-2 bg-panel border border-line rounded-xl p-5 shadow-sm">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="text-slate-900 font-semibold">Revenue vs Expenses</h3>
          <p class="text-xs text-muted">January – July 2025</p>
        </div>
        <div class="flex items-center gap-3 text-[11px] text-muted">
          <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background:#4f46e5"></span>Revenue</span>
          <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background:#14b8a6"></span>Expenses</span>
          <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background:#22c55e"></span>Profit</span>
        </div>
      </div>
      @php
        $data = [
          ['Jan', 180, 130], ['Feb', 200, 140], ['Mar', 175, 135],
          ['Apr', 220, 150], ['May', 240, 160], ['Jun', 210, 150], ['Jul', 235, 141],
        ];
        // SVG plot geometry
        $w = 760; $h = 220; $padL = 44; $padR = 14; $padTop = 12; $padBot = 26;
        $maxY = 260;
        $n = count($data);
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padTop - $padBot;
        $baseY = $padTop + $plotH;
        $x = fn($i) => $padL + ($n > 1 ? $i * $plotW / ($n - 1) : 0);
        $y = fn($v) => $padTop + $plotH - ($v / $maxY) * $plotH;

        // Catmull-Rom -> cubic bezier for a smooth line path
        $smoothPath = function (array $pts) {
            if (count($pts) < 2) return '';
            $d = 'M ' . $pts[0][0] . ' ' . $pts[0][1];
            $m = count($pts);
            for ($i = 0; $i < $m - 1; $i++) {
                $p0 = $pts[$i - 1] ?? $pts[$i];
                $p1 = $pts[$i];
                $p2 = $pts[$i + 1];
                $p3 = $pts[$i + 2] ?? $p2;
                $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
                $c1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
                $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
                $c2y = $p2[1] - ($p3[1] - $p1[1]) / 6;
                $d .= sprintf(' C %.2f %.2f %.2f %.2f %.2f %.2f', $c1x, $c1y, $c2x, $c2y, $p2[0], $p2[1]);
            }
            return $d;
        };

        $series = [
          ['key' => 'rev', 'color' => '#4f46e5', 'values' => array_map(fn($d) => $d[1], $data)],
          ['key' => 'exp', 'color' => '#14b8a6', 'values' => array_map(fn($d) => $d[2], $data)],
          ['key' => 'prf', 'color' => '#22c55e', 'values' => array_map(fn($d) => $d[1] - $d[2], $data)],
        ];
        foreach ($series as &$s) {
            $s['pts'] = array_map(fn($v, $i) => [$x($i), $y($v)], $s['values'], array_keys($s['values']));
            $s['line'] = $smoothPath($s['pts']);
            $s['area'] = $s['line'] . sprintf(' L %.2f %.2f L %.2f %.2f Z', $x($n - 1), $baseY, $x(0), $baseY);
        }
        unset($s);
      @endphp
      <div class="mt-6">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-56">
          <defs>
            @foreach($series as $s)
              <linearGradient id="grad-{{ $s['key'] }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="{{ $s['color'] }}" stop-opacity="0.20"/>
                <stop offset="100%" stop-color="{{ $s['color'] }}" stop-opacity="0"/>
              </linearGradient>
            @endforeach
          </defs>

          {{-- gridlines + y-axis labels --}}
          @foreach([0, 60, 120, 180, 240] as $g)
            <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}"
                  stroke="#e2e8f0" stroke-width="1" stroke-dasharray="{{ $g == 0 ? '0' : '3 3' }}"
                  vector-effect="non-scaling-stroke"/>
            <text x="{{ $padL - 8 }}" y="{{ $y($g) + 3 }}" fill="#94a3b8" font-size="11"
                  text-anchor="end" font-family="Inter, sans-serif">${{ $g }}k</text>
          @endforeach

          {{-- area fills --}}
          @foreach($series as $s)
            <path d="{{ $s['area'] }}" fill="url(#grad-{{ $s['key'] }})"/>
          @endforeach

          {{-- smooth lines --}}
          @foreach($series as $s)
            <path d="{{ $s['line'] }}" fill="none" stroke="{{ $s['color'] }}"
                  stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"
                  vector-effect="non-scaling-stroke"/>
          @endforeach

          {{-- x-axis labels --}}
          @foreach($data as $i => $d)
            <text x="{{ $x($i) }}" y="{{ $h - 6 }}" fill="#94a3b8" font-size="11"
                  text-anchor="middle" font-family="Inter, sans-serif">{{ $d[0] }}</text>
          @endforeach
        </svg>
      </div>
    </div>

    {{-- Crew by rank --}}
    <div class="bg-panel border border-line rounded-xl p-5 shadow-sm">
      <h3 class="text-slate-900 font-semibold">Crew by Rank</h3>
      <p class="text-xs text-muted">{{ collect($ranks)->sum('count') }} total crew</p>
      <div class="mt-5 space-y-3">
        @foreach($ranks as $r)
          <div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-700">{{ $r['name'] }}</span>
              <span class="text-muted">{{ $r['count'] }}</span>
            </div>
            <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-brand to-brand-d" style="width: {{ $r['pct'] }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Fleet + Activity --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-panel border border-line rounded-xl shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-line">
        <h3 class="text-slate-900 font-semibold">Fleet Status</h3>
        <a href="{{ route('crew.index') }}" class="text-xs text-brand hover:text-brand-d font-medium">Manage crew →</a>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50">
              <th class="text-left px-5 py-2 font-medium">Vessel</th>
              <th class="text-left px-3 py-2 font-medium">Status</th>
              <th class="text-left px-3 py-2 font-medium">Crew</th>
              <th class="text-left px-3 py-2 font-medium">Principal</th>
              <th class="text-left px-5 py-2 font-medium">ETA</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fleet as $v)
              @php
                $colors = [
                  'At Sea'   => 'bg-blue-50 text-blue-700 border-blue-200',
                  'In Port'  => 'bg-green-50 text-green-700 border-green-200',
                  'Dry Dock' => 'bg-amber-50 text-amber-700 border-amber-200',
                ];
              @endphp
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-5 py-3">
                  <div class="text-slate-900 font-medium">{{ $v['vessel'] }}</div>
                  <div class="text-[11px] text-muted">{{ $v['type'] }}</div>
                </td>
                <td class="px-3 py-3">
                  <span class="chip border {{ $colors[$v['status']] ?? '' }}">{{ $v['status'] }}</span>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $v['crew'] }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $v['principal'] }}</td>
                <td class="px-5 py-3 text-slate-600">{{ $v['eta'] }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-5 py-8 text-center text-muted">No vessels yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Activity --}}
    <div class="bg-panel border border-line rounded-xl p-5 shadow-sm">
      <h3 class="text-slate-900 font-semibold mb-4">Recent Crew Activity</h3>
      <ul class="space-y-4">
        @forelse($activity as $a)
          @php
            $dot = match($a['type']){
              'signon'  => 'bg-brand',
              'signoff' => 'bg-slate-400',
              'warn'    => 'bg-amber-400',
              'pay'     => 'bg-accent',
              'error'   => 'bg-rose-400',
              default   => 'bg-slate-400',
            };
          @endphp
          <li class="flex gap-3">
            <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $dot }}"></span>
            <div class="flex-1 min-w-0">
              <div class="text-[13px] text-slate-700 leading-snug">{{ $a['title'] }}</div>
              @if($a['sub'])
                <div class="text-[11px] text-muted">{{ $a['sub'] }}</div>
              @endif
              <div class="text-[10px] text-muted mt-0.5">{{ $a['time'] }}</div>
            </div>
          </li>
        @empty
          <li class="text-sm text-muted">No activity yet.</li>
        @endforelse
      </ul>
    </div>
  </div>
@endsection
