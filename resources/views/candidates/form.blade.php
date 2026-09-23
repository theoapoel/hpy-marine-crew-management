@php
  /** @var \App\Models\CrewCandidate|null $candidate */
  $candidate = $candidate ?? null;

  $val = function ($field, $default = '') use ($candidate) {
      $current = old($field, $candidate->{$field} ?? $default);

      return $current instanceof \Illuminate\Support\Carbon ? $current->format('Y-m-d') : $current;
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';
  $text = fn ($v) => ucwords(str_replace('_', ' ', (string) $v));

  $cops = old('cop_certificates', $candidate->cop_certificates ?? []);
  $cops[] = ['name' => '', 'number' => '', 'expiry' => ''];

  // Sections are collapsible; the first one starts open.
  $sections = [
    'personal' => 'Personal Info',
    'professional' => 'Professional',
    'certification' => 'Certification',
    'source' => 'Source & Status',
    'attachments' => 'Attachments',
  ];
@endphp

@if($errors->any())
  <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

{{-- Actions ride along at the top of the form --}}
<div class="sticky top-16 z-10 -mx-4 lg:-mx-8 px-4 lg:px-8 py-3 bg-canvas/95 backdrop-blur border-b border-line flex items-center gap-3">
  <button name="after_save" value="close" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-5 py-2 shadow-sm">Save &amp; Close</button>
  <button name="after_save" value="new" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm font-medium rounded-md px-5 py-2">Save &amp; New</button>
  <a href="{{ route('candidates.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

{{-- Duplicate warning, filled by the NIK / seaman book lookup below --}}
<div id="duplicate-warning" class="hidden bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
  <div class="font-medium">A similar candidate is already in the pool</div>
  <ul id="duplicate-list" class="list-disc list-inside mt-1 space-y-0.5"></ul>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    @foreach($sections as $key => $heading)
      <button type="button" data-tab-target="{{ $key }}"
              class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">{{ $heading }}</button>
    @endforeach
  </div>

@foreach($sections as $key => $heading)
  <div data-tab-panel="{{ $key }}" class="bg-panel border border-line rounded-xl shadow-sm">
    <div class="px-6 py-6">

      @if($key === 'personal')
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Personal Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-6 min-w-0">
            <label class="{{ $label }}">Full Name *</label>
            <input type="text" name="full_name" required value="{{ $val('full_name') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Gender</label>
            <select name="gender" class="{{ $input }}">
              <option value="">—</option>
              @foreach($genders as $g)<option value="{{ $g }}" @selected($val('gender') === $g)>{{ $text($g) }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Date of Birth *</label>
            <input type="date" name="date_of_birth" required value="{{ $val('date_of_birth') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Place of Birth</label>
            <input type="text" name="place_of_birth" value="{{ $val('place_of_birth') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Nationality</label>
            <input type="text" name="nationality" value="{{ $val('nationality', 'Indonesia') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Marital Status</label>
            <select name="marital_status" class="{{ $input }}">
              <option value="">—</option>
              @foreach($maritalStatuses as $m)<option value="{{ $m }}" @selected($val('marital_status') === $m)>{{ $text($m) }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Religion</label>
            <input type="text" name="religion" value="{{ $val('religion') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Blood Type</label>
            <input type="text" name="blood_type" value="{{ $val('blood_type') }}" class="{{ $input }}">
          </div>
        </div>
      @endif

      @if($key === 'personal')
        <div class="mt-8 pt-6 border-t border-line"><h3 class="text-sm font-semibold text-slate-900 mb-4">Identity</h3></div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">NIK (KTP)</label>
            <input type="text" name="nik" id="nik" inputmode="numeric" maxlength="16" value="{{ $val('nik') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Passport No.</label>
            <input type="text" name="passport_no" value="{{ $val('passport_no') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Passport Expiry</label>
            <input type="date" name="passport_expiry" value="{{ $val('passport_expiry') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Passport Issue Place</label>
            <input type="text" name="passport_issue_place" value="{{ $val('passport_issue_place') }}" class="{{ $input }}">
          </div>
        </div>
      @endif

      @if($key === 'personal')
        <div class="mt-8 pt-6 border-t border-line"><h3 class="text-sm font-semibold text-slate-900 mb-4">Contact</h3></div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Phone *</label>
            <input type="text" name="phone" required value="{{ $val('phone') }}" placeholder="081234567890" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">WhatsApp</label>
            <input type="text" name="whatsapp" value="{{ $val('whatsapp') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-6 min-w-0">
            <label class="{{ $label }}">Email</label>
            <input type="email" name="email" value="{{ $val('email') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-12 min-w-0">
            <label class="{{ $label }}">Address</label>
            <textarea name="address" rows="2" class="{{ $input }}">{{ $val('address') }}</textarea>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">City</label>
            <input type="text" name="city" value="{{ $val('city') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Province</label>
            <input type="text" name="province" value="{{ $val('province') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Postal Code</label>
            <input type="text" name="postal_code" value="{{ $val('postal_code') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Emergency Contact</label>
            <input type="text" name="emergency_contact_name" value="{{ $val('emergency_contact_name') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Relationship</label>
            <input type="text" name="emergency_contact_relation" value="{{ $val('emergency_contact_relation') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Emergency Phone</label>
            <input type="text" name="emergency_contact_phone" value="{{ $val('emergency_contact_phone') }}" class="{{ $input }}">
          </div>
        </div>
      @endif

      @if($key === 'professional')
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Applied Rank</label>
            <select name="applied_rank" class="{{ $input }}">
              <option value="">—</option>
              @foreach($ranks as $r)<option value="{{ $r }}" @selected($val('applied_rank') === $r)>{{ $r }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Preferred Vessel Type</label>
            <input type="text" name="preferred_vessel_type" value="{{ $val('preferred_vessel_type') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Experience (years)</label>
            <input type="number" name="years_of_experience" min="0" max="70" value="{{ $val('years_of_experience', 0) }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Last Sign Off</label>
            <input type="date" name="last_sign_off_date" value="{{ $val('last_sign_off_date') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-6 min-w-0">
            <label class="{{ $label }}">Last Vessel</label>
            <input type="text" name="last_vessel_name" value="{{ $val('last_vessel_name') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Last Rank</label>
            <input type="text" name="last_rank" value="{{ $val('last_rank') }}" class="{{ $input }}">
          </div>
        </div>
      @endif

      @if($key === 'certification')
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Seaman Book</h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Seaman Book No.</label>
            <input type="text" name="seaman_book_no" id="seaman_book_no" value="{{ $val('seaman_book_no') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Seaman Book Expiry</label>
            <input type="date" name="seaman_book_expiry" value="{{ $val('seaman_book_expiry') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Seaman Book Issue Place</label>
            <input type="text" name="seaman_book_issue_place" value="{{ $val('seaman_book_issue_place') }}" class="{{ $input }}">
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-line flex items-center justify-between gap-3 mb-4" data-type-anchor="coc">
          <h3 class="text-sm font-semibold text-slate-900">COC &amp; Medical</h3>
          @if($cocTypesEditable)
            <button type="button" data-type-new="coc" data-url="{{ route('candidates.coc-types.store') }}" data-type-label="COC type"
                    class="text-xs text-slate-600 hover:text-slate-900 font-medium">+ New COC type</button>
          @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">COC Type</label>
            <select name="coc_type" data-type-select="coc" class="{{ $input }}">
              @foreach(collect($cocTypes)->push($val('coc_type'))->filter()->unique() as $c)<option value="{{ $c }}" @selected($val('coc_type', 'None') === $c)>{{ $c }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">COC Number</label>
            <input type="text" name="coc_number" value="{{ $val('coc_number') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">COC Expiry</label>
            <input type="date" name="coc_expiry" value="{{ $val('coc_expiry') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">MCU Expiry</label>
            <input type="date" name="mcu_expiry" value="{{ $val('mcu_expiry') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">ENDC Expiry</label>
            <input type="date" name="endc_expiry" value="{{ $val('endc_expiry') }}" class="{{ $input }}">
          </div>
        </div>

        <div class="mt-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-slate-700">COP Certificates</span>
            <button type="button" id="add-cop" class="text-xs text-brand hover:text-brand-d font-medium">+ Add row</button>
          </div>
          <div id="cop-rows" class="space-y-2">
            @foreach($cops as $i => $cop)
              <div class="cop-row grid grid-cols-1 md:grid-cols-12 gap-2">
                <input type="text" name="cop_certificates[{{ $i }}][name]" value="{{ $cop['name'] ?? '' }}" placeholder="Certificate name (BST, AFF, ...)" class="{{ $input }} md:col-span-6">
                <input type="text" name="cop_certificates[{{ $i }}][number]" value="{{ $cop['number'] ?? '' }}" placeholder="Number" class="{{ $input }} md:col-span-3">
                <input type="date" name="cop_certificates[{{ $i }}][expiry]" value="{{ $cop['expiry'] ?? '' }}" aria-label="Expiry" class="{{ $input }} md:col-span-3">
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if($key === 'source')
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Source *</label>
            <select name="source" id="source" class="{{ $input }}">
              @foreach($sources as $s)<option value="{{ $s }}" @selected($val('source', 'walk_in') === $s)>{{ $text($s) }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Source Detail</label>
            <input type="text" name="source_detail" value="{{ $val('source_detail') }}" class="{{ $input }}">
          </div>

          <div data-source-field="referral">
            <label class="{{ $label }}">Referred by (Employee ID)</label>
            <input type="text" name="referred_by_employee_id" value="{{ $val('referred_by_employee_id') }}" placeholder="HR-EMP-00001" class="{{ $input }}">
          </div>
          <div data-source-field="agency">
            <label class="{{ $label }}">Agency (Supplier)</label>
            <input type="text" name="source_agency_id" value="{{ $val('source_agency_id') }}" class="{{ $input }}">
          </div>
          <div data-source-field="school">
            <label class="{{ $label }}">School / Academy</label>
            <input type="text" name="source_school" value="{{ $val('source_school') }}" class="{{ $input }}">
          </div>
          <div data-source-field="cost">
            <label class="{{ $label }}">Recruitment Cost</label>
            <input type="number" step="0.01" name="source_cost" value="{{ $val('source_cost') }}" class="{{ $input }}">
          </div>

          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Status *</label>
            <select name="status" class="{{ $input }}">
              @foreach($statuses as $s)<option value="{{ $s }}" @selected($val('status', 'applicant') === $s)>{{ $text($s) }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Availability Date</label>
            <input type="date" name="availability_date" value="{{ $val('availability_date') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Expected Salary</label>
            <input type="number" step="0.01" name="expected_salary" value="{{ $val('expected_salary') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Currency</label>
            <input type="text" name="expected_salary_currency" value="{{ $val('expected_salary_currency', 'IDR') }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-12 min-w-0">
            <label class="{{ $label }}">Notes</label>
            <textarea name="notes" rows="3" class="{{ $input }}">{{ $val('notes') }}</textarea>
          </div>
        </div>
      @endif

      @if($key === 'attachments')
        @php $fileInput = $input . ' py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs'; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          <div class="min-w-0">
            <label class="{{ $label }}">Photo</label>
            <div class="flex items-center gap-3">
              <img id="photo-preview" src="{{ $candidate?->photo_path ? Storage::url($candidate->photo_path) : '' }}"
                   class="w-12 h-12 rounded-lg object-cover border border-line shrink-0 {{ $candidate?->photo_path ? '' : 'hidden' }}" alt="">
              <input type="file" name="photo" accept="image/*" id="photo-input" class="{{ $fileInput }} min-w-0">
            </div>
          </div>

          @foreach(['cv' => 'CV', 'id_scan' => 'ID Card Scan (KTP)', 'seaman_book_scan' => 'Scan Seaman Book', 'coc_scan' => 'Scan COC'] as $field => $heading2)
            @php $stored = $candidate?->{$field . '_path'}; @endphp
            <div class="min-w-0">
              <label class="{{ $label }}">{{ $heading2 }}</label>
              <input type="file" name="{{ $field }}" accept="image/*,application/pdf" class="{{ $fileInput }}">
              @if($stored)
                <button type="button" data-preview="{{ Storage::url($stored) }}" data-preview-title="{{ $heading2 }} — {{ basename($stored) }}"
                        class="mt-1 text-[11px] text-brand hover:underline">Preview current file ({{ basename($stored) }})</button>
              @endif
            </div>
          @endforeach

          <div class="min-w-0">
            <label class="{{ $label }}">Other documents</label>
            <input type="file" name="other_documents[]" multiple class="{{ $fileInput }}">
            @foreach($candidate?->other_documents ?? [] as $doc)
              <button type="button" data-preview="{{ Storage::url($doc['path']) }}" data-preview-title="{{ $doc['name'] }}"
                      class="block mt-1 text-[11px] text-brand hover:underline truncate max-w-full">{{ $doc['name'] }}</button>
            @endforeach
          </div>
        </div>
      @endif

    </div>
  </div>
@endforeach
</div>


<script>
  (function () {
    // Conditional source fields
    var source = document.getElementById('source');
    var paidSources = @json($paidSources);

    function refreshSourceFields() {
      document.querySelectorAll('[data-source-field]').forEach(function (field) {
        var want = field.dataset.sourceField;
        var show = want === 'cost' ? paidSources.indexOf(source.value) !== -1 : source.value === want;
        field.classList.toggle('hidden', ! show);
      });
    }
    source.addEventListener('change', refreshSourceFields);
    refreshSourceFields();

    // Duplicate detection on NIK / seaman book
    var warning = document.getElementById('duplicate-warning');
    var list = document.getElementById('duplicate-list');
    var timer;

    function checkDuplicates() {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var params = new URLSearchParams({
          nik: document.getElementById('nik').value,
          seaman_book_no: document.getElementById('seaman_book_no').value,
          ignore: @json($candidate?->uuid ?? ''),
        });

        fetch('{{ route('candidates.duplicates') }}?' + params)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            list.innerHTML = '';
            data.matches.forEach(function (match) {
              var li = document.createElement('li');
              li.innerHTML = match.field + ' matches <a class="underline font-medium" href="' + match.url + '">' +
                match.code + ' — ' + match.name + '</a> (' + match.status + ')';
              list.appendChild(li);
            });
            warning.classList.toggle('hidden', data.matches.length === 0);
          });
      }, 400);
    }

    ['nik', 'seaman_book_no'].forEach(function (id) {
      document.getElementById(id).addEventListener('input', checkDuplicates);
    });

    // Photo preview
    var photoInput = document.getElementById('photo-input');
    photoInput.addEventListener('change', function () {
      if (! photoInput.files.length) { return; }
      var preview = document.getElementById('photo-preview');
      preview.src = URL.createObjectURL(photoInput.files[0]);
      preview.classList.remove('hidden');
    });

    // COP certificate repeater
    document.getElementById('add-cop').addEventListener('click', function () {
      var rows = document.querySelectorAll('.cop-row');
      var clone = rows[rows.length - 1].cloneNode(true);
      var index = rows.length;

      clone.querySelectorAll('input').forEach(function (field) {
        field.name = field.name.replace(/cop_certificates\[\d+]/, 'cop_certificates[' + index + ']');
        field.value = '';
      });

      document.getElementById('cop-rows').appendChild(clone);
    });
  })();
</script>
