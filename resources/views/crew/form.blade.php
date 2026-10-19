@php
  /** @var \App\Models\Crew|null $crew */
  $crew = $crew ?? null;

  $val = function ($field, $default = '') use ($crew) {
      $current = old($field, $crew->{$field} ?? $default);

      return $current instanceof \Illuminate\Support\Carbon ? $current->format('Y-m-d') : $current;
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';
  $fileInput = $input . ' py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs';
  $tab = 'px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900';

  // Existing rows, plus one blank row to fill in. Passport and seaman book are rows too.
  $certificates = old('certificates', collect($crew->certificates ?? [])->map(fn ($c) => (array) $c)->all());
  $certificates[] = [];

  $bankAccounts = old('bank_accounts', collect($crew->bank_accounts ?? [])->map(fn ($a) => (array) $a)->all());
  $bankAccounts[] = [];

  $previewable = fn (?string $path) => filled($path) ? route('erp.file', ['path' => $path]) : null;

  // The sea service record only exists once the crew member does (edit, not create).
  $seaService = $seaService ?? null;
  $canAppraise = $canAppraise ?? false;
  $fmtDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : '—';
  $appraisalColors = [
    'Excellent' => 'bg-green-50 text-green-700 border-green-200',
    'Very Good' => 'bg-green-50 text-green-700 border-green-200',
    'Good' => 'bg-sky-50 text-sky-700 border-sky-200',
    'Fair' => 'bg-amber-50 text-amber-700 border-amber-200',
    'Poor' => 'bg-rose-50 text-rose-700 border-rose-200',
  ];
@endphp

@if($errors->any())
  <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

{{-- Actions ride along at the top of the form, under the page header --}}
<div class="sticky top-16 z-10 -mx-4 lg:-mx-8 px-4 lg:px-8 py-3 bg-canvas/95 backdrop-blur border-b border-line flex items-center gap-3">
  <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-5 py-2 shadow-sm">{{ $submitLabel }}</button>
  <a href="{{ route('crew.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5" role="tablist">
    <button type="button" data-tab-target="overview" class="{{ $tab }}">Overview</button>
    <button type="button" data-tab-target="assignment" class="{{ $tab }}">Assignment</button>
    <button type="button" data-tab-target="seafarer" class="{{ $tab }}">Seafarer Details &amp; Contacts</button>
    <button type="button" data-tab-target="bank" class="{{ $tab }}">Bank Details</button>
    <button type="button" data-tab-target="certificates" class="{{ $tab }}">Certificates &amp; Documents</button>
    @if($seaService !== null)
      <button type="button" data-tab-target="performance-appraisal" class="{{ $tab }}">Performance Appraisal</button>
    @endif
  </div>

{{-- Overview --}}
<div data-tab-panel="overview" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Salutation</label>
      <select name="salutation" class="{{ $input }}">
        <option value="">—</option>
        @foreach($salutations as $s)
          <option value="{{ $s }}" @selected($val('salutation') === $s)>{{ $s }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">First Name *</label>
      <input type="text" name="first_name" required value="{{ $val('first_name') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Middle Name</label>
      <input type="text" name="middle_name" value="{{ $val('middle_name') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Last Name</label>
      <input type="text" name="last_name" value="{{ $val('last_name') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Gender *</label>
      <select name="gender" required class="{{ $input }}">
        @foreach($genders as $g)
          <option value="{{ $g }}" @selected($val('gender') === $g)>{{ $g }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Date of Birth *</label>
      <input type="date" name="date_of_birth" required value="{{ $val('date_of_birth') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Date of Joining *</label>
      <input type="date" name="date_of_joining" required value="{{ $val('date_of_joining') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Photo</label>
      <div class="flex items-center gap-3">
        @if($crew?->image)
          <button type="button" data-preview="{{ $previewable($crew->image) }}" data-preview-title="{{ $crew->name }} — photo"
                  class="shrink-0 rounded-lg ring-offset-2 hover:ring-2 hover:ring-brand/40" aria-label="Preview photo">
            <img src="{{ $previewable($crew->image) }}" alt="{{ $crew->name }}" class="w-12 h-12 rounded-lg object-cover border border-line">
          </button>
        @endif
        <input type="file" name="photo" accept="image/*" class="{{ $fileInput }}">
      </div>
      @if($crew?->image)
        <p class="text-[11px] text-muted mt-1">Leave empty to keep this photo.</p>
      @endif
    </div>
  </div>
</div>
</div>

{{-- Assignment --}}
<div data-tab-panel="assignment" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Rank *</label>
      <select name="rank" required class="{{ $input }}">
        @foreach($ranks as $r)
          <option value="{{ $r }}" @selected($val('rank') === $r)>{{ $r }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Crew Status *</label>
      <select name="status" required class="{{ $input }}">
        @foreach($statuses as $s)
          <option value="{{ $s }}" @selected($val('status') === $s)>{{ $s }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Vessel</label>
      <select name="vessel" class="{{ $input }}">
        <option value="">— Unassigned —</option>
        @foreach($vessels as $v)
          <option value="{{ $v }}" @selected($val('vessel') === $v)>{{ $v }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Employee Number</label>
      <input type="text" name="employee_number" value="{{ $val('employee_number') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Department</label>
      <select name="department" class="{{ $input }}">
        <option value="">—</option>
        @foreach($departments as $d)
          <option value="{{ $d }}" @selected($val('department') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Sign On Date</label>
      <input type="date" name="sign_on_date" value="{{ $val('sign_on_date') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Contract End Date</label>
      <input type="date" name="contract_end_date" value="{{ $val('contract_end_date') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Seafarer details & contacts --}}
<div data-tab-panel="seafarer" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <h3 class="text-sm font-semibold text-slate-900 mb-4">Seafarer Details</h3>
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Nationality</label>
      <input type="text" name="nationality" value="{{ $val('nationality', 'Indonesian') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Blood Group</label>
      <select name="blood_group" class="{{ $input }}">
        <option value="">—</option>
        @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
          <option value="{{ $bg }}" @selected($val('blood_group') === $bg)>{{ $bg }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Marital Status</label>
      <select name="marital_status" class="{{ $input }}">
        <option value="">—</option>
        @foreach(['Single','Married','Divorced','Widowed'] as $ms)
          <option value="{{ $ms }}" @selected($val('marital_status') === $ms)>{{ $ms }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Health Details</label>
      <input type="text" name="health_details" value="{{ $val('health_details') }}" class="{{ $input }}">
    </div>
  </div>
  <p class="mt-4 text-[11px] text-muted">Passport and Seaman Book are entered on the <span class="font-medium text-slate-600">Certificates &amp; Documents</span> tab.</p>
</div>

<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <h3 class="text-sm font-semibold text-slate-900 mb-4">Address &amp; Contacts</h3>
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Mobile</label>
      <input type="text" name="cell_number" value="{{ $val('cell_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Personal Email</label>
      <input type="email" name="personal_email" value="{{ $val('personal_email') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Company Email</label>
      <input type="email" name="company_email" value="{{ $val('company_email') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Current Address</label>
      <textarea name="current_address" rows="3" class="{{ $input }}">{{ $val('current_address') }}</textarea>
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Permanent Address</label>
      <textarea name="permanent_address" rows="3" class="{{ $input }}">{{ $val('permanent_address') }}</textarea>
    </div>

    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Emergency Contact Name</label>
      <input type="text" name="person_to_be_contacted" value="{{ $val('person_to_be_contacted') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Emergency Phone</label>
      <input type="text" name="emergency_phone_number" value="{{ $val('emergency_phone_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Relation</label>
      <input type="text" name="relation" value="{{ $val('relation') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Bank details: last salary + one row per account --}}
<div data-tab-panel="bank" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <h3 class="text-sm font-semibold text-slate-900 mb-4">Last Salary</h3>
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Currency</label>
      <select name="last_salary_currency" class="{{ $input }}">
        <option value="">—</option>
        @foreach($currencies as $c)
          <option value="{{ $c }}" @selected($val('last_salary_currency', 'IDR') === $c)>{{ $c }}</option>
        @endforeach
      </select>
    </div>
    <div class="md:col-span-5 min-w-0">
      <label class="{{ $label }}">Last Salary</label>
      <input type="number" name="last_salary" min="0" step="0.01" inputmode="decimal" value="{{ $val('last_salary') }}" class="{{ $input }} tabular-nums">
    </div>
  </div>
</div>

<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4" data-repeater="bank_accounts">
  <div class="flex items-center justify-between">
    <div>
      <h3 class="text-sm font-semibold text-slate-900">Bank Accounts</h3>
      <p class="text-[11px] text-muted mt-0.5">The first account is the main (payroll) account.</p>
    </div>
    <button type="button" data-repeater-add class="text-xs text-brand hover:text-brand-d font-medium">+ Add account</button>
  </div>

  <div data-repeater-rows class="space-y-3">
    @foreach($bankAccounts as $i => $account)
      <div data-repeater-row class="border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Account</span>
          <button type="button" data-repeater-remove class="text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-4 min-w-0">
            <label class="{{ $label }}">Bank Name</label>
            <input type="text" name="bank_accounts[{{ $i }}][bank_name]" value="{{ $account['bank_name'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-4 min-w-0">
            <label class="{{ $label }}">Account Holder Name</label>
            <input type="text" name="bank_accounts[{{ $i }}][account_holder]" value="{{ $account['account_holder'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-4 min-w-0">
            <label class="{{ $label }}">Account Number</label>
            <input type="text" name="bank_accounts[{{ $i }}][account_number]" value="{{ $account['account_number'] ?? '' }}" class="{{ $input }} tabular-nums">
          </div>
          <div class="md:col-span-12 min-w-0">
            <label class="{{ $label }}">Bank Address</label>
            <textarea name="bank_accounts[{{ $i }}][bank_address]" rows="2" class="{{ $input }}">{{ $account['bank_address'] ?? '' }}</textarea>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
</div>

{{-- Certificates & documents, passport and seaman book included --}}
<div data-tab-panel="certificates" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4" data-repeater="certificates">
  <div class="flex flex-wrap items-center justify-between gap-3" data-type-anchor="cert">
    <p class="text-[11px] text-muted">Passport, Seaman Book and other certificates — one row per document.</p>
    <div class="flex items-center gap-4">
      @if($certificateTypesEditable)
        <button type="button" data-type-new="cert" data-type-label="certificate type" data-url="{{ route('crew.certificate-types.store') }}"
                class="text-xs text-slate-600 hover:text-slate-900 font-medium">+ New type</button>
      @endif
      <button type="button" data-repeater-add class="text-xs text-brand hover:text-brand-d font-medium">+ Add row</button>
    </div>
  </div>

  <div data-repeater-rows class="space-y-3">
    @foreach($certificates as $i => $row)
      <div data-repeater-row class="border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Document</span>
          <button type="button" data-repeater-remove class="text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Type</label>
          <select name="certificates[{{ $i }}][certificate_type]" data-type-select="cert" class="{{ $input }}">
            <option value="">—</option>
            @foreach(collect($certificateTypes)->push($row['certificate_type'] ?? null)->filter()->unique() as $t)
              <option value="{{ $t }}" @selected(($row['certificate_type'] ?? '') === $t)>{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Number</label>
          <input type="text" name="certificates[{{ $i }}][certificate_number]" value="{{ $row['certificate_number'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Issued By / Place of Issue</label>
          <input type="text" name="certificates[{{ $i }}][issued_by]" value="{{ $row['issued_by'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">Issue Date</label>
          <input type="date" name="certificates[{{ $i }}][issue_date]" value="{{ $row['issue_date'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">Expiry Date</label>
          <input type="date" name="certificates[{{ $i }}][expiry_date]" value="{{ $row['expiry_date'] ?? '' }}" class="{{ $input }}">
        </div>

        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">File</label>
          <input type="file" name="certificates[{{ $i }}][file]" accept="image/*,application/pdf" class="{{ $fileInput }}">
          <input type="hidden" name="certificates[{{ $i }}][attachment]" value="{{ $row['attachment'] ?? '' }}">
          @if(!empty($row['attachment']))
            <button type="button" data-repeater-clear data-preview="{{ $previewable($row['attachment']) }}"
                    data-preview-title="{{ ($row['certificate_type'] ?? 'Document') . ' — ' . basename($row['attachment']) }}"
                    class="mt-1 inline-flex items-center gap-1 text-[11px] text-brand hover:underline">
              Preview current file ({{ basename($row['attachment']) }})
            </button>
          @endif
        </div>
        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">Remarks</label>
          <input type="text" name="certificates[{{ $i }}][remarks]" value="{{ $row['remarks'] ?? '' }}" class="{{ $input }}">
        </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
</div>

@if($seaService !== null)
{{-- Performance appraisal: the sea service record, one berth per row; grades save with the form. --}}
<div data-tab-panel="performance-appraisal" class="space-y-5">
<div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
  <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-6 pb-4">
    <div>
      <h2 class="text-sm font-semibold text-slate-900">Sea Service Record</h2>
      <p class="text-xs text-muted mt-0.5">Every berth through us, latest first. Grades are saved with the form.</p>
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
            $grade = old("appraisals.{$berth->uuid}.appraisal", $berth->appraisal);
            $notes = old("appraisals.{$berth->uuid}.notes", $berth->appraisal_notes);
          @endphp
          <tr class="border-b border-line last:border-0 align-top">
            <td class="px-6 py-3 text-muted tabular-nums">{{ $loop->iteration }}.</td>
            <td class="px-3 py-3 text-slate-900 font-medium">{{ $berth->rank ?: '—' }}</td>
            <td class="px-3 py-3 text-slate-900">{{ $ship['vessel_name'] ?? $berth->vessel ?: '—' }}</td>
            <td class="px-3 py-3 text-slate-600">{{ $ship['principal'] ?? $berth->company ?: '—' }}</td>
            <td class="px-3 py-3 text-slate-600">{{ $ship['vessel_type'] ?? '—' }}</td>
            <td class="px-3 py-3 text-slate-600 text-right tabular-nums">{{ filled($ship['gross_tonnage'] ?? null) ? number_format((float) $ship['gross_tonnage']) : '—' }}</td>
            <td class="px-3 py-3 text-slate-600 whitespace-nowrap">{{ $fmtDate($berth->sign_on_date) }}</td>
            <td class="px-3 py-3 text-slate-600 whitespace-nowrap">
              @if($berth->sign_off_date)
                {{ $fmtDate($berth->sign_off_date) }}
              @else
                <span class="chip border bg-green-50 text-green-700 border-green-200">Onboard</span>
              @endif
            </td>
            <td class="px-3 py-3">
              @if($canAppraise)
                <select name="appraisals[{{ $berth->uuid }}][appraisal]" class="{{ $input }} py-1 min-w-36">
                  <option value="">— Not rated —</option>
                  @foreach(\App\Models\CrewAssignment::APPRAISALS as $option)
                    <option value="{{ $option }}" @selected($grade === $option)>{{ $option }}</option>
                  @endforeach
                </select>
              @elseif($grade)
                <span class="chip border {{ $appraisalColors[$grade] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">{{ $grade }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="px-6 py-3">
              @if($canAppraise)
                <textarea name="appraisals[{{ $berth->uuid }}][notes]" rows="2" placeholder="Describe how they performed on this contract"
                          class="{{ $input }} py-1.5 min-w-64 resize-y">{{ $notes }}</textarea>
              @else
                <div class="text-slate-600 whitespace-pre-line max-w-72">{{ $notes ?: '—' }}</div>
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
@endif

</div>
