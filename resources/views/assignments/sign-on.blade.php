@extends('layouts.app')

@section('title', 'Sign On · HPYMarine')
@section('heading', 'Sign On')

@section('content')
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Sign On</h1>
      <p class="text-sm text-muted mt-1">{{ $assignments->count() }} crew waiting to join</p>
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search crew name..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="vessel" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">All vessels</option>
      @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected(($filters['vessel'] ?? '') === $vessel)>{{ $vessel }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
  </form>

  <div class="space-y-3">
    @forelse($assignments as $assignment)
      <div class="bg-panel border border-line rounded-xl p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[220px]">
            <a href="{{ route('assignments.show', $assignment) }}" class="text-sm font-medium text-slate-900 hover:text-brand">{{ $assignment->crew_name }}</a>
            <div class="text-[11px] text-muted">
              {{ $assignment->assignment_code }} · {{ $assignment->rank ?: '—' }} · {{ $assignment->vessel ?: 'vessel not set' }}
            </div>
            @if($assignment->planned_sign_on_date)
              <div class="text-[11px] text-muted">Planned: {{ $assignment->planned_sign_on_date->format('d M Y') }}</div>
            @endif
          </div>

          @can('assignments.update')
            <form method="POST" action="{{ route('assignments.do-sign-on', $assignment) }}" class="flex flex-wrap items-end gap-2 ml-auto" data-contract>
              @csrf
              <div>
                <label class="block text-xs text-muted mb-1">Sign On Date</label>
                <input type="date" name="sign_on_date" data-contract-start value="{{ now()->format('Y-m-d') }}"
                       class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Port</label>
                <input type="text" name="sign_on_port" class="w-36 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Contract (months)</label>
                <input type="number" name="contract_months" data-contract-months min="1" max="36" value="{{ $assignment->contract_months ?: 6 }}"
                       class="w-24 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <div>
                <label class="block text-xs text-muted mb-1">Planned Sign Off</label>
                <input type="date" name="planned_sign_off_date" data-contract-end
                       class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
              <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2">Sign On</button>
            </form>
          @endcan
        </div>
      </div>
    @empty
      <div class="bg-panel border border-line rounded-xl p-10 text-center text-muted text-sm shadow-sm">
        No crew waiting to sign on.
      </div>
    @endforelse
  </div>

  {{-- Who boarded lately, whether signed on here or from the assignment form. --}}
  @if($recent->isNotEmpty())
    <section class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-line">
        <h2 class="text-sm font-semibold text-slate-900">Recently Signed On</h2>
        <p class="text-[11px] text-muted">Last 30 days · {{ $recent->count() }} crew</p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
              <th class="text-left px-5 py-2.5 font-medium">Crew</th>
              <th class="text-left px-3 py-2.5 font-medium">Vessel</th>
              <th class="text-left px-3 py-2.5 font-medium">Sign On</th>
              <th class="text-left px-3 py-2.5 font-medium">Port</th>
              <th class="text-left px-3 py-2.5 font-medium">Planned Sign Off</th>
              <th class="text-left px-5 py-2.5 font-medium">ERP HPY</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recent as $assignment)
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-5 py-3">
                  <a href="{{ route('assignments.show', $assignment) }}" class="font-medium text-slate-900 hover:text-brand">{{ $assignment->crew_name }}</a>
                  <div class="text-[11px] text-muted">{{ $assignment->assignment_code }} · {{ $assignment->rank ?: '—' }}</div>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->vessel ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->sign_on_date?->format('d M Y') }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->sign_on_port ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $assignment->planned_sign_off_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-5 py-3">
                  @if($assignment->employee_id)
                    <span class="chip border bg-emerald-50 text-emerald-700 border-emerald-200">{{ $assignment->employee_id }}</span>
                  @else
                    <span class="chip border bg-amber-50 text-amber-700 border-amber-200" title="Not linked to an Employee — Crew Master is not updated">not linked</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif

  <p class="text-[11px] text-muted">Sign on also updates the Employee in ERP HPY: crew status becomes Onboard, and the vessel and contract dates are filled in.</p>
@endsection
