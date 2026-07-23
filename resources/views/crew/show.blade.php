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
        <img src="{{ $file($crew->image) }}" alt="{{ $crew->name }}" class="w-16 h-16 rounded-xl object-cover border border-line">
      @endif
      <div>
        <a href="{{ route('crew.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Crew Master</a>
        <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $crew->name }}</h1>
        <p class="text-sm text-muted mt-1">
          {{ $crew->rank ?: '—' }} · {{ $crew->nationality ?: '—' }} · {{ $crew->id }}
        </p>
      </div>
    </div>
    <a href="{{ route('crew.edit', $crew) }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Edit</a>
  </div>

  @php
    $groups = [
      'Assignment' => [
        'Crew Status' => $crew->status,
        'Rank' => $crew->rank,
        'Vessel' => $crew->vessel,
        'Company' => $crew->company,
        'Department' => $crew->department,
        'Branch' => $crew->branch,
        'Employee Number' => $crew->employee_number,
        'Employee Status' => $crew->employee_status,
        'Sign On Date' => $fmt($crew->sign_on_date),
        'Contract End Date' => $fmt($crew->contract_end_date),
        'Date of Joining' => $fmt($crew->date_of_joining),
      ],
      'Seafarer & Personal' => [
        'Nationality' => $crew->nationality,
        'Seaman Book No.' => $crew->seaman_book_no,
        'Seaman Book Expiry' => $fmt($crew->seaman_book_expiry),
        'Gender' => $crew->gender,
        'Date of Birth' => $fmt($crew->date_of_birth),
        'Marital Status' => $crew->marital_status,
        'Blood Group' => $crew->blood_group,
        'Health Details' => $crew->health_details,
      ],
      'Passport' => [
        'Passport Number' => $crew->passport_number,
        'Date of Issue' => $fmt($crew->date_of_issue),
        'Valid Upto' => $fmt($crew->valid_upto),
        'Place of Issue' => $crew->place_of_issue,
      ],
      'Address & Contacts' => [
        'Mobile' => $crew->cell_number,
        'Personal Email' => $crew->personal_email,
        'Company Email' => $crew->company_email,
        'Current Address' => $crew->current_address,
        'Permanent Address' => $crew->permanent_address,
        'Emergency Contact' => $crew->person_to_be_contacted,
        'Emergency Phone' => $crew->emergency_phone_number,
        'Relation' => $crew->relation,
      ],
      'Bank' => [
        'Bank Name' => $crew->bank_name,
        'Bank A/C No.' => $crew->bank_ac_no,
      ],
    ];
  @endphp

  <div data-tab-group>
    <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
      @foreach(array_keys($groups) as $groupName)
        <button type="button" data-tab-target="{{ Str::slug($groupName) }}"
                class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">{{ $groupName }}</button>
      @endforeach
      <button type="button" data-tab-target="certificates" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Certificates &amp; Documents</button>
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
                  <a href="{{ $file($certificate->attachment) }}" target="_blank" rel="noopener"
                     class="text-brand hover:text-brand-d font-medium">Download</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-muted">Belum ada sertifikat.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  </div>
@endsection
