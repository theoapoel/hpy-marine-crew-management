@extends('layouts.app')

@section('title', $crew->name . ' · HPYMarine')
@section('heading', 'Crew Master')

@section('content')
  @php
    $file = fn ($path) => route('erp.file', ['path' => $path]);
    $fmt = function ($value) {
        if (blank($value)) { return '—'; }
        try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y'); }
        catch (\Throwable) { return $value; }
    };
    $certColors = [
      'Valid' => 'bg-green-50 text-green-700 border-green-200',
      'Expiring Soon' => 'bg-amber-50 text-amber-700 border-amber-200',
      'Expired' => 'bg-rose-50 text-rose-700 border-rose-200',
      'Revoked' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div class="flex items-start gap-4">
      @if($crew->image)
        <button type="button" data-preview="{{ $file($crew->image) }}" data-preview-title="{{ $crew->name }} — photo" aria-label="Preview photo"
                class="shrink-0 rounded-xl ring-offset-2 hover:ring-2 hover:ring-brand/40">
          <img src="{{ $file($crew->image) }}" alt="{{ $crew->name }}" class="w-16 h-16 rounded-xl object-cover border border-line">
        </button>
      @endif
      <div>
        <a href="{{ route('crew.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Crew Master</a>
        <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $crew->name }}</h1>
        <p class="text-sm text-muted mt-1">
          {{ $crew->rank ?: '—' }} · {{ $crew->nationality ?: '—' }} · {{ $crew->id }}
        </p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('crew.cv', $crew->id) }}" target="_blank"
         class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2 inline-flex items-center gap-1.5">
        <i data-lucide="printer" class="size-4"></i>Print CV
      </a>
      <a href="{{ route('crew.cv', [$crew->id, 'download' => 1]) }}"
         class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2 inline-flex items-center gap-1.5">
        <i data-lucide="file-down" class="size-4"></i>Download PDF
      </a>
      <a href="{{ route('crew.edit', $crew) }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Edit</a>
    </div>
  </div>

  @php
    $groups = [
      'Assignment' => [
        'Crew Status' => $crew->status,
        'Rank' => $crew->rank,
        'Vessel' => $crew->vessel,
        'Company' => $crew->company,
        'Department' => $crew->department,
        'Employee Number' => $crew->employee_number,
        'Employee Status' => $crew->employee_status,
        'Sign On Date' => $fmt($crew->sign_on_date),
        'Contract End Date' => $fmt($crew->contract_end_date),
        'Date of Joining' => $fmt($crew->date_of_joining),
      ],
      'Seafarer Details & Contacts' => [
        'Nationality' => $crew->nationality,
        'Gender' => $crew->gender,
        'Date of Birth' => $fmt($crew->date_of_birth),
        'Marital Status' => $crew->marital_status,
        'Blood Group' => $crew->blood_group,
        'Health Details' => $crew->health_details,
        'Mobile' => $crew->cell_number,
        'Personal Email' => $crew->personal_email,
        'Company Email' => $crew->company_email,
        'Current Address' => $crew->current_address,
        'Permanent Address' => $crew->permanent_address,
        'Emergency Contact' => $crew->person_to_be_contacted,
        'Emergency Phone' => $crew->emergency_phone_number,
        'Relation' => $crew->relation,
      ],
    ];
    $salary = filled($crew->last_salary)
      ? trim(($crew->last_salary_currency ?? '') . ' ' . number_format((float) $crew->last_salary, 2))
      : null;
  @endphp

  <div data-tab-group>
    <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
      @foreach(array_keys($groups) as $groupName)
        <button type="button" data-tab-target="{{ Str::slug($groupName) }}"
                class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">{{ $groupName }}</button>
      @endforeach
      <button type="button" data-tab-target="bank" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Bank Details</button>
      <button type="button" data-tab-target="certificates" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Certificates &amp; Documents</button>
      <button type="button" data-tab-target="performance-appraisal" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Performance Appraisal</button>
    </div>

  @foreach($groups as $group => $rows)
    <div data-tab-panel="{{ Str::slug($group) }}" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ $group }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
        @foreach($rows as $labelText => $value)
          <div>
            <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
            <div class="text-slate-800 mt-1 font-medium">{{ filled($value) ? $value : '—' }}</div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach

  {{-- Bank details --}}
  <div data-tab-panel="bank" class="space-y-5">
    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      <div class="text-[11px] uppercase tracking-wider text-muted">Last Salary</div>
      <div class="text-xl font-semibold text-slate-900 mt-1 tabular-nums">{{ $salary ?? '—' }}</div>
    </div>

    <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
      <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-4">Bank Accounts</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
              <th class="text-left px-6 py-3 font-medium">Bank</th>
              <th class="text-left px-3 py-3 font-medium">Account Holder</th>
              <th class="text-left px-3 py-3 font-medium">Account Number</th>
              <th class="text-left px-6 py-3 font-medium">Bank Address</th>
            </tr>
          </thead>
          <tbody>
            @forelse($crew->bank_accounts ?? [] as $account)
              <tr class="border-b border-line last:border-0">
                <td class="px-6 py-3 text-slate-900 font-medium">
                  {{ $account->bank_name ?? '—' }}
                  @if($loop->first && count($crew->bank_accounts) > 1)
                    <span class="chip border bg-brand/5 text-brand border-brand/20 ml-1">Primary</span>
                  @endif
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $account->account_holder ?? '—' }}</td>
                <td class="px-3 py-3 text-slate-600 tabular-nums">{{ $account->account_number ?? '—' }}</td>
                <td class="px-6 py-3 text-slate-600 whitespace-pre-line">{{ $account->bank_address ?? '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-6 py-10 text-center text-muted">No bank accounts yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Certificates --}}
  <div data-tab-panel="certificates" class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
    <h2 class="text-sm font-semibold text-slate-900 px-6 pt-6 pb-4">Certificates &amp; Documents</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
            <th class="text-left px-6 py-3 font-medium">Type</th>
            <th class="text-left px-3 py-3 font-medium">Number</th>
            <th class="text-left px-3 py-3 font-medium">Issued By</th>
            <th class="text-left px-3 py-3 font-medium">Issue</th>
            <th class="text-left px-3 py-3 font-medium">Expiry</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-right px-6 py-3 font-medium">File</th>
          </tr>
        </thead>
        <tbody>
          @forelse($crew->certificates ?? [] as $certificate)
            <tr class="border-b border-line last:border-0">
              <td class="px-6 py-3 text-slate-900 font-medium">{{ $certificate->certificate_type ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $certificate->certificate_number ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $certificate->issued_by ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($certificate->issue_date ?? null) }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $fmt($certificate->expiry_date ?? null) }}</td>
              <td class="px-3 py-3">
                <span class="chip border {{ $certColors[$certificate->status ?? ''] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                  {{ $certificate->status ?? '—' }}
                </span>
              </td>
              <td class="px-6 py-3 text-right">
                @if(!empty($certificate->attachment))
                  <button type="button" data-preview="{{ $file($certificate->attachment) }}"
                          data-preview-title="{{ ($certificate->certificate_type ?? 'Document') . ' — ' . basename($certificate->attachment) }}"
                          class="text-brand hover:text-brand-d font-medium">Preview</button>
                  <span class="text-line mx-1">|</span>
                  <a href="{{ $file($certificate->attachment) }}" download class="text-slate-600 hover:text-slate-900">Download</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-muted">No certificates yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Performance appraisal: the sea service record, one berth per row, graded. --}}
  @php
    $appraisalColors = [
      'Excellent' => 'bg-green-50 text-green-700 border-green-200',
      'Very Good' => 'bg-green-50 text-green-700 border-green-200',
      'Good' => 'bg-sky-50 text-sky-700 border-sky-200',
      'Fair' => 'bg-amber-50 text-amber-700 border-amber-200',
      'Poor' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
  @endphp
  <div data-tab-panel="performance-appraisal" class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-6 pb-4">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Sea Service Record</h2>
        <p class="text-xs text-muted mt-0.5">Every berth through us, latest first, with how they did on each.</p>
      </div>
      <span class="text-xs text-muted">{{ $seaService->count() }} {{ Str::plural('contract', $seaService->count()) }}</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-y border-line">
            <th class="text-left px-6 py-3 font-medium">No.</th>
            <th class="text-left px-3 py-3 font-medium">Rank</th>
            <th class="text-left px-3 py-3 font-medium">Ship Name</th>
            <th class="text-left px-3 py-3 font-medium">Company</th>
            <th class="text-left px-3 py-3 font-medium">Ship Type</th>
            <th class="text-right px-3 py-3 font-medium">GRT</th>
            <th class="text-left px-3 py-3 font-medium">Sign On</th>
            <th class="text-left px-3 py-3 font-medium">Sign Off</th>
            <th class="text-left px-3 py-3 font-medium">Appraisal</th>
            <th class="text-left px-6 py-3 font-medium">Remarks</th>
          </tr>
        </thead>
        <tbody>
          @forelse($seaService as $berth)
            @php
              $ship = $berth->vessel_details ?? [];
              $formId = 'appraisal-' . $berth->uuid;
            @endphp
            <tr class="border-b border-line last:border-0 align-top">
              <td class="px-6 py-3 text-muted tabular-nums">{{ $loop->iteration }}.</td>
              <td class="px-3 py-3 text-slate-900 font-medium">{{ $berth->rank ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-900">
                @if($berth->vessel)
                  <a href="{{ route('vessels.show', $berth->vessel) }}" class="hover:text-brand">{{ $ship['vessel_name'] ?? $berth->vessel }}</a>
                @else
                  —
                @endif
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $ship['principal'] ?? $berth->company ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $ship['vessel_type'] ?? '—' }}</td>
              <td class="px-3 py-3 text-slate-600 text-right tabular-nums">{{ filled($ship['gross_tonnage'] ?? null) ? number_format((float) $ship['gross_tonnage']) : '—' }}</td>
              <td class="px-3 py-3 text-slate-600 whitespace-nowrap">{{ $fmt($berth->sign_on_date) }}</td>
              <td class="px-3 py-3 text-slate-600 whitespace-nowrap">
                @if($berth->sign_off_date)
                  {{ $fmt($berth->sign_off_date) }}
                @else
                  <span class="chip border bg-green-50 text-green-700 border-green-200">Onboard</span>
                @endif
              </td>
              @php $field = 'bg-white border border-line rounded-md px-2 text-sm placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
              <td class="px-3 py-3">
                @if($canAppraise)
                  {{-- The select and the remarks textarea in the next cell both belong to this form. --}}
                  <form method="POST" action="{{ route('crew.appraise', [$crew->id, $berth]) }}" id="{{ $formId }}">
                    @csrf
                    <select name="appraisal" class="{{ $field }} py-1 min-w-36">
                      <option value="">— Not rated —</option>
                      @foreach(\App\Models\CrewAssignment::APPRAISALS as $grade)
                        <option value="{{ $grade }}" @selected($berth->appraisal === $grade)>{{ $grade }}</option>
                      @endforeach
                    </select>
                  </form>
                @elseif($berth->appraisal)
                  <span class="chip border {{ $appraisalColors[$berth->appraisal] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">{{ $berth->appraisal }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="px-6 py-3">
                @if($canAppraise)
                  <div class="flex items-start gap-2">
                    <textarea name="appraisal_notes" form="{{ $formId }}" rows="2" placeholder="Describe how they performed on this contract"
                              class="{{ $field }} py-1.5 min-w-64 resize-y">{{ $berth->appraisal_notes }}</textarea>
                    <button form="{{ $formId }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-xs rounded-md px-2.5 py-1.5 shrink-0">Save</button>
                  </div>
                @else
                  <div class="text-slate-600 whitespace-pre-line max-w-72">{{ $berth->appraisal_notes ?: '—' }}</div>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="px-6 py-10 text-center text-muted">No sea service through us yet. Berths appear here once a crew assignment is signed on.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  </div>
@endsection
