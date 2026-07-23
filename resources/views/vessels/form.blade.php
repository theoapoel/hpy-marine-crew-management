@php
  /** @var object|null $vessel */
  $vessel = $vessel ?? null;

  $val = function ($field, $default = '') use ($vessel) {
      return old($field, $vessel->{$field} ?? $default);
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';

  // Existing certificate rows, plus a blank one to add another.
  $rows = old('certificates', collect($certificates ?? [])->map(fn ($c) => (array) $c)->all());
  $rows[] = [];
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
  <a href="{{ route('vessels.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    <button type="button" data-tab-target="identification" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Identification</button>
    <button type="button" data-tab-target="ownership-and-management" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Ownership &amp; Management</button>
    <button type="button" data-tab-target="build" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Build</button>
    <button type="button" data-tab-target="dimensions-and-tonnage" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Dimensions &amp; Tonnage</button>
    <button type="button" data-tab-target="vessel-certificates" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Vessel Certificates</button>
  </div>
<div data-tab-panel="identification" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-5">
  <div class="flex items-center justify-between">
    <span class="text-[11px] text-muted">Company: <span class="font-medium text-slate-700">{{ $company ?: '—' }}</span></span>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Vessel Name *</label>
      <input type="text" name="vessel_name" required value="{{ $val('vessel_name') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Vessel Type *</label>
      <select name="vessel_type" required class="{{ $input }}">
        <option value="">—</option>
        @foreach($types as $type)<option value="{{ $type }}" @selected($val('vessel_type') === $type)>{{ $type }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sub-Type</label>
      <input type="text" name="sub_type" value="{{ $val('sub_type') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">IMO Number</label>
      <input type="text" name="imo_number" value="{{ $val('imo_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">MMSI</label>
      <input type="text" name="mmsi_number" value="{{ $val('mmsi_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Call Sign</label>
      <input type="text" name="call_sign" value="{{ $val('call_sign') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Official Number</label>
      <input type="text" name="official_number" value="{{ $val('official_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Previous Name (Ex-Name)</label>
      <input type="text" name="ex_name" value="{{ $val('ex_name') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Vessel Photo</label>
      <div class="flex items-center gap-3">
        @if($vessel?->vessel_photo ?? null)
          <img src="{{ route('erp.file', ['path' => $vessel->vessel_photo]) }}" alt=""
               class="w-16 h-12 rounded object-cover border border-line shrink-0">
        @endif
        <input type="file" name="photo" accept="image/*" class="{{ $input }} py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
      </div>
      @if($vessel?->vessel_photo ?? null)
        <p class="text-[11px] text-muted mt-1">Leave empty to keep this photo.</p>
      @endif
    </div>

    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">GA Drawing</label>
      <input type="file" name="ga_drawing_file" class="{{ $input }} py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
      @if($vessel?->ga_drawing ?? null)
        <a href="{{ route('erp.file', ['path' => $vessel->ga_drawing]) }}" target="_blank" rel="noopener"
           class="text-[11px] text-brand hover:underline">View current file</a>
      @endif
    </div>
  </div>
</div>
</div>

<div data-tab-panel="ownership-and-management" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Principal</label>
      <select name="principal" class="{{ $input }}">
        <option value="">— none —</option>
        @foreach($principals as $erpName => $principalName)
          <option value="{{ $erpName }}" @selected($val('principal') === $erpName)>{{ $principalName }}</option>
        @endforeach
      </select>
      <p class="text-[11px] text-muted mt-1">Owner or manager this vessel is crewed for.</p>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Flag State</label>
      <select name="flag_state" class="{{ $input }}">
        <option value="">—</option>
        @foreach($countries as $country)<option value="{{ $country }}" @selected($val('flag_state') === $country)>{{ $country }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Port of Registry</label>
      <input type="text" name="port_of_registry" value="{{ $val('port_of_registry') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Date of Registration</label>
      <input type="date" name="date_of_registration" value="{{ $val('date_of_registration') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Classification Society</label>
      <select name="classification_society" class="{{ $input }}">
        <option value="">—</option>
        @foreach($societies as $society)<option value="{{ $society }}" @selected($val('classification_society') === $society)>{{ $society }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Class Number</label>
      <input type="text" name="class_number" value="{{ $val('class_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Class Notation</label>
      <input type="text" name="class_notation" value="{{ $val('class_notation') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

<div data-tab-panel="build" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Year Built</label>
      <input type="number" name="year_built" min="1900" max="{{ now()->year + 5 }}" value="{{ $val('year_built') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Builder</label>
      <input type="text" name="builder" value="{{ $val('builder') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Country of Build</label>
      <select name="country_of_build" class="{{ $input }}">
        <option value="">—</option>
        @foreach($countries as $country)<option value="{{ $country }}" @selected($val('country_of_build') === $country)>{{ $country }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Hull Number</label>
      <input type="text" name="hull_number" value="{{ $val('hull_number') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Keel Laid</label>
      <input type="date" name="keel_laid_date" value="{{ $val('keel_laid_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Delivery</label>
      <input type="date" name="delivery_date" value="{{ $val('delivery_date') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

<div data-tab-panel="dimensions-and-tonnage" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    @foreach([
      'length_overall' => 'LOA (m)', 'length_bp' => 'LBP (m)', 'breadth' => 'Breadth (m)',
      'depth_moulded' => 'Depth Moulded (m)', 'draft_summer' => 'Summer Draft (m)',
      'draft_ballast' => 'Ballast Draft (m)', 'freeboard' => 'Freeboard (m)',
      'gross_tonnage' => 'Gross Tonnage', 'net_tonnage' => 'Net Tonnage',
      'deadweight' => 'Deadweight', 'displacement' => 'Displacement (MT)',
      'cargo_capacity' => 'Cargo Capacity (m³)', 'fuel_capacity' => 'Fuel (MT)',
      'fresh_water_capacity' => 'Fresh Water (MT)', 'ballast_capacity' => 'Ballast (MT)',
      'service_speed' => 'Service Speed (kn)', 'max_speed' => 'Max Speed (kn)',
    ] as $field => $heading)
      <div>
        <label class="{{ $label }}">{{ $heading }}</label>
        <input type="number" step="0.01" min="0" name="{{ $field }}" value="{{ $val($field) }}" class="{{ $input }}">
      </div>
    @endforeach
  </div>
</div>
</div>

<div data-tab-panel="vessel-certificates" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4">
  <div class="flex items-center justify-end">
    <button type="button" id="add-certificate" class="text-xs text-brand hover:text-brand-d font-medium">+ Add row</button>
  </div>

  <div id="certificate-rows" class="space-y-3">
    @foreach($rows as $i => $row)
      <div class="certificate-row border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Certificate</span>
          <button type="button" class="remove-certificate text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Type</label>
          <select name="certificates[{{ $i }}][certificate_type]" class="{{ $input }}">
            <option value="">—</option>
            @foreach($certificateTypes as $type)
              <option value="{{ $type }}" @selected(($row['certificate_type'] ?? '') === $type)>{{ $type }}</option>
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
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Issued</label>
          <input type="date" name="certificates[{{ $i }}][issue_date]" value="{{ $row['issue_date'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Expiry</label>
          <input type="date" name="certificates[{{ $i }}][expiry_date]" value="{{ $row['expiry_date'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">Status</label>
          <select name="certificates[{{ $i }}][status]" class="{{ $input }}">
            <option value="">auto from expiry date</option>
            @foreach($certificateStatuses as $status)
              <option value="{{ $status }}" @selected(($row['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-6 min-w-0">
          <label class="{{ $label }}">File</label>
          <input type="file" name="certificates[{{ $i }}][file]" class="{{ $input }} py-1.5 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs">
          <input type="hidden" name="certificates[{{ $i }}][attachment]" value="{{ $row['attachment'] ?? '' }}">
          @if(!empty($row['attachment']))
            <a href="{{ route('erp.file', ['path' => $row['attachment']]) }}" target="_blank" rel="noopener"
               class="text-[11px] text-brand hover:underline">View current file ({{ basename($row['attachment']) }})</a>
          @endif
        </div>
        <div class="md:col-span-12 min-w-0">
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
        field.value = '';
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