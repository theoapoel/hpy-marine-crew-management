@php
  /** @var \App\Models\Crew|null $crew */
  $crew = $crew ?? null;

  $val = function ($field, $default = '') use ($crew) {
      $current = old($field, $crew->{$field} ?? $default);

      return $current instanceof \Illuminate\Support\Carbon ? $current->format('Y-m-d') : $current;
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';

  // Existing certificate rows, plus one blank row to add another.
  $certificates = old('certificates', collect($crew->certificates ?? [])->map(fn ($c) => (array) $c)->all());
  $certificates[] = [];
@endphp

@if($errors->any())
  <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

{{-- Actions ride along at the top of the form --}}
<div class="sticky top-0 z-20 -mx-6 px-6 py-3 bg-canvas/95 backdrop-blur border-b border-line flex items-center gap-3">
  <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-5 py-2 shadow-sm">{{ $submitLabel }}</button>
  <a href="{{ route('crew.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

{{-- Overview --}}
<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    <button type="button" data-tab-target="overview" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Overview</button>
    <button type="button" data-tab-target="assignment" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Assignment</button>
    <button type="button" data-tab-target="seafarer-details" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Seafarer Details</button>
    <button type="button" data-tab-target="passport" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Passport</button>
    <button type="button" data-tab-target="address-and-contacts" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Address &amp; Contacts</button>
    <button type="button" data-tab-target="bank-details" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Bank Details</button>
    <button type="button" data-tab-target="certificates-and-documents" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Certificates &amp; Documents</button>
  </div>
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
          <img src="{{ route('erp.file', ['path' => $crew->image]) }}" alt="{{ $crew->name }}"
               class="w-12 h-12 rounded-lg object-cover border border-line shrink-0">
        @endif
        <input type="file" name="photo" accept="image/*" class="{{ $input }} py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
      </div>
      @if($crew?->image)
        <p class="text-[11px] text-muted mt-1">Biarkan kosong untuk mempertahankan foto ini.</p>
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

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Department</label>
      <select name="department" class="{{ $input }}">
        <option value="">—</option>
        @foreach($departments as $d)
          <option value="{{ $d }}" @selected($val('department') === $d)>{{ $d }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Branch</label>
      <select name="branch" class="{{ $input }}">
        <option value="">—</option>
        @foreach($branches as $b)
          <option value="{{ $b }}" @selected($val('branch') === $b)>{{ $b }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign On Date</label>
      <input type="date" name="sign_on_date" value="{{ $val('sign_on_date') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Contract End Date</label>
      <input type="date" name="contract_end_date" value="{{ $val('contract_end_date') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Seafarer details --}}
<div data-tab-panel="seafarer-details" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Nationality</label>
      <input type="text" name="nationality" value="{{ $val('nationality', 'Indonesian') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Seaman Book No.</label>
      <input type="text" name="seaman_book_no" value="{{ $val('seaman_book_no') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Seaman Book Expiry</label>
      <input type="date" name="seaman_book_expiry" value="{{ $val('seaman_book_expiry') }}" class="{{ $input }}">
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

    <div class="md:col-span-9 min-w-0">
      <label class="{{ $label }}">Health Details</label>
      <input type="text" name="health_details" value="{{ $val('health_details') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Passport --}}
<div data-tab-panel="passport" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Passport Number</label>
      <input type="text" name="passport_number" value="{{ $val('passport_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Date of Issue</label>
      <input type="date" name="date_of_issue" value="{{ $val('date_of_issue') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Valid Upto</label>
      <input type="date" name="valid_upto" value="{{ $val('valid_upto') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Place of Issue</label>
      <input type="text" name="place_of_issue" value="{{ $val('place_of_issue') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Contact --}}
<div data-tab-panel="address-and-contacts" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
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

{{-- Bank --}}
<div data-tab-panel="bank-details" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Bank Name</label>
      <input type="text" name="bank_name" value="{{ $val('bank_name') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-4 min-w-0">
      <label class="{{ $label }}">Bank A/C No.</label>
      <input type="text" name="bank_ac_no" value="{{ $val('bank_ac_no') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

{{-- Certificates --}}
<div data-tab-panel="certificates-and-documents" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4">
  <div class="flex items-center justify-end">
    <button type="button" id="add-certificate" class="text-xs text-brand hover:text-brand-d font-medium">+ Add row</button>
  </div>

  <div id="certificate-rows" class="space-y-3">
    @foreach($certificates as $i => $row)
      <div class="certificate-row border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Document</span>
          <button type="button" class="remove-certificate text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Type</label>
          <select name="certificates[{{ $i }}][certificate_type]" class="{{ $input }}">
            <option value="">—</option>
            @foreach($certificateTypes as $t)
              <option value="{{ $t }}" @selected(($row['certificate_type'] ?? '') === $t)>{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Number</label>
          <input type="text" name="certificates[{{ $i }}][certificate_number]" value="{{ $row['certificate_number'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Issued By</label>
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
          <input type="file" name="certificates[{{ $i }}][file]" class="{{ $input }} py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
          <input type="hidden" name="certificates[{{ $i }}][attachment]" value="{{ $row['attachment'] ?? '' }}">
          @if(!empty($row['attachment']))
            <a href="{{ route('erp.file', ['path' => $row['attachment']]) }}" target="_blank" rel="noopener"
               class="text-[11px] text-brand hover:underline">Lihat file saat ini ({{ basename($row['attachment']) }})</a>
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


<script>
  // Certificate rows are a plain repeater: clone the last row, renumber its inputs.
  (function () {
    const list = document.getElementById('certificate-rows');

    document.getElementById('add-certificate').addEventListener('click', function () {
      const rows = list.querySelectorAll('.certificate-row');
      const clone = rows[rows.length - 1].cloneNode(true);
      const index = rows.length;

      clone.querySelectorAll('input, select').forEach(function (field) {
        field.name = field.name.replace(/certificates\[\d+]/, 'certificates[' + index + ']');
        if (field.type === 'file') { field.value = ''; } else { field.value = ''; }
      });
      clone.querySelectorAll('a').forEach(function (link) { link.remove(); });

      list.appendChild(clone);
    });

    list.addEventListener('click', function (event) {
      if (! event.target.classList.contains('remove-certificate')) { return; }
      const rows = list.querySelectorAll('.certificate-row');
      if (rows.length === 1) { return; }
      event.target.closest('.certificate-row').remove();
    });
  })();
</script>

</div>