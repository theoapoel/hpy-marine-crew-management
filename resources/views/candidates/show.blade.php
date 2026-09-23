@extends('layouts.app')

@section('title', $candidate->full_name . ' · HPYMarine')
@section('heading', 'Candidate Pool')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $fmt = fn ($d) => $d ? $d->format('d M Y') : '—';
    $statusColors = [
      'applicant'  => 'bg-blue-50 text-blue-700 border-blue-200',
      'in_process' => 'bg-amber-50 text-amber-700 border-amber-200',
      'employed'   => 'bg-green-50 text-green-700 border-green-200',
      'on_leave'   => 'bg-slate-100 text-slate-600 border-slate-200',
      'blacklist'  => 'bg-rose-50 text-rose-700 border-rose-200',
      'retired'    => 'bg-slate-100 text-slate-500 border-slate-200',
    ];

    // Expired papers are recorded, not rejected — surface them here instead.
    $expiries = [
      'Passport' => $candidate->passport_expiry,
      'Seaman Book' => $candidate->seaman_book_expiry,
      'COC' => $candidate->coc_expiry,
      'MCU' => $candidate->mcu_expiry,
      'ENDC' => $candidate->endc_expiry,
    ];
    $expired = collect($expiries)->filter(fn ($d) => $d && $d->isPast());
  @endphp

  <div class="flex items-start justify-between">
    <div class="flex items-start gap-4">
      @if($candidate->photo_path)
        <img src="{{ Storage::url($candidate->photo_path) }}" alt="{{ $candidate->full_name }}"
             class="w-16 h-16 rounded-xl object-cover border border-line">
      @endif
      <div>
        <a href="{{ route('candidates.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Candidate Pool</a>
        <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $candidate->full_name }}</h1>
        <p class="text-sm text-muted mt-1">
          <span class="font-mono">{{ $candidate->candidate_code }}</span> ·
          {{ $candidate->applied_rank ?: 'Rank belum ditentukan' }} ·
          {{ $candidate->age ? $candidate->age . ' tahun' : '—' }}
        </p>
        <div class="mt-2 flex items-center gap-2">
          <span class="chip border {{ $statusColors[$candidate->status] ?? '' }}">{{ $text($candidate->status) }}</span>
          @if($candidate->is_available)
            <span class="chip border bg-green-50 text-green-700 border-green-200">Available</span>
          @endif
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      @can('candidates.update')
        <a href="{{ route('candidates.edit', $candidate) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
        @if(! $candidate->linked_employee_id)
          <form method="POST" action="{{ route('candidates.promote', $candidate) }}"
                onsubmit="return confirm('Buat Employee di ERP HPY dari kandidat ini?')">
            @csrf
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">Promote to Employee</button>
          </form>
        @endif
      @endcan
    </div>
  </div>

  @if($candidate->linked_employee_id)
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
      Sudah menjadi Employee
      <a href="{{ route('crew.show', $candidate->linked_employee_id) }}" class="underline font-medium">{{ $candidate->linked_employee_id }}</a>
      di ERP HPY.
    </div>
  @endif

  @if($expired->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
      Dokumen kedaluwarsa:
      {{ $expired->map(fn ($d, $name) => $name . ' (' . $d->format('d M Y') . ')')->implode(', ') }}
    </div>
  @endif

  @php
    $groups = [
      'Personal Info' => [
        'Gender' => $text($candidate->gender),
        'Tanggal Lahir' => $fmt($candidate->date_of_birth),
        'Tempat Lahir' => $candidate->place_of_birth,
        'Kewarganegaraan' => $candidate->nationality,
        'Status Pernikahan' => $text($candidate->marital_status),
        'Agama' => $candidate->religion,
        'Golongan Darah' => $candidate->blood_type,
        'NIK' => $candidate->nik,
        'Passport No.' => $candidate->passport_no,
        'Passport Expiry' => $fmt($candidate->passport_expiry),
        'Passport Issue Place' => $candidate->passport_issue_place,
        'Telepon' => $candidate->phone,
        'WhatsApp' => $candidate->whatsapp,
        'Email' => $candidate->email,
        'Alamat' => $candidate->full_address,
        'Kontak Darurat' => $candidate->emergency_contact_name,
        'Hubungan' => $candidate->emergency_contact_relation,
        'Telepon Darurat' => $candidate->emergency_contact_phone,
      ],
      'Professional' => [
        'Applied Rank' => $candidate->applied_rank,
        'Preferred Vessel Type' => $candidate->preferred_vessel_type,
        'Pengalaman' => $candidate->years_of_experience . ' tahun',
        'Kapal Terakhir' => $candidate->last_vessel_name,
        'Rank Terakhir' => $candidate->last_rank,
        'Last Sign Off' => $fmt($candidate->last_sign_off_date),
      ],
      'Certification' => [
        'Seaman Book No.' => $candidate->seaman_book_no,
        'Seaman Book Expiry' => $fmt($candidate->seaman_book_expiry),
        'Seaman Book Issue Place' => $candidate->seaman_book_issue_place,
        'COC Type' => $candidate->coc_type,
        'COC Number' => $candidate->coc_number,
        'COC Expiry' => $fmt($candidate->coc_expiry),
        'MCU Expiry' => $fmt($candidate->mcu_expiry),
        'ENDC Expiry' => $fmt($candidate->endc_expiry),
      ],
      'Source & Status' => [
        'Source' => $text($candidate->source),
        'Source Detail' => $candidate->source_detail,
        'Direferensikan oleh' => $candidate->referred_by_employee_id,
        'Agency' => $candidate->source_agency_id,
        'Sekolah' => $candidate->source_school,
        'Biaya Rekrutmen' => $candidate->source_cost ? number_format((float) $candidate->source_cost, 2) : null,
        'Availability Date' => $fmt($candidate->availability_date),
        'Expected Salary' => $candidate->expected_salary ? $candidate->expected_salary_currency . ' ' . number_format((float) $candidate->expected_salary, 2) : null,
      ],
    ];
  @endphp

  <div data-tab-group>
    <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
      @foreach(array_keys($groups) as $groupName)
        <button type="button" data-tab-target="{{ Str::slug($groupName) }}"
                class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">{{ $groupName }}</button>
      @endforeach
      <button type="button" data-tab-target="cop" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">COP Certificates</button>
      <button type="button" data-tab-target="attachments" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Attachments</button>
      <button type="button" data-tab-target="notes" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Notes</button>
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

  {{-- COP certificates --}}
  <div data-tab-panel="cop" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">COP Certificates</h2>
    @forelse($candidate->cop_certificates ?? [] as $cop)
      <div class="flex items-center gap-6 text-sm border-b border-line last:border-0 py-2">
        <div class="font-medium text-slate-800 w-64">{{ $cop['name'] ?? '—' }}</div>
        <div class="text-slate-600 w-48">{{ $cop['number'] ?? '—' }}</div>
        <div class="text-slate-600">{{ !empty($cop['expiry']) ? \Illuminate\Support\Carbon::parse($cop['expiry'])->format('d M Y') : '—' }}</div>
      </div>
    @empty
      <p class="text-sm text-muted">Belum ada COP.</p>
    @endforelse
  </div>

  {{-- Attachments --}}
  <div data-tab-panel="attachments" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">Attachments</h2>
    <div class="flex flex-wrap gap-4 text-sm">
      @foreach(['cv_path' => 'CV', 'id_scan_path' => 'Scan KTP', 'seaman_book_scan_path' => 'Scan Seaman Book', 'coc_scan_path' => 'Scan COC'] as $field => $heading)
        @if($candidate->{$field})
          <a href="{{ Storage::url($candidate->{$field}) }}" target="_blank" rel="noopener"
             class="text-brand hover:text-brand-d font-medium">{{ $heading }}</a>
        @endif
      @endforeach
      @foreach($candidate->other_documents ?? [] as $doc)
        <a href="{{ Storage::url($doc['path']) }}" target="_blank" rel="noopener" class="text-brand hover:text-brand-d font-medium">{{ $doc['name'] }}</a>
      @endforeach
    </div>
  </div>

  <div data-tab-panel="notes" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-2">Catatan</h2>
    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $candidate->notes ?: 'Belum ada catatan.' }}</p>
  </div>
  </div>

  <p class="text-[11px] text-muted">
    Dibuat {{ $candidate->created_at?->format('d M Y H:i') }} oleh {{ $candidate->created_by ?? '—' }} ·
    Diubah {{ $candidate->updated_at?->format('d M Y H:i') }} oleh {{ $candidate->updated_by ?? '—' }}
  </p>
@endsection
