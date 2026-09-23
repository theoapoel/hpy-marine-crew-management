@php
  /** @var \App\Models\Principal|null $principal */
  $principal = $principal ?? null;

  $val = function ($field, $default = '') use ($principal) {
      $current = old($field, $principal->{$field} ?? $default);

      return $current instanceof \Illuminate\Support\Carbon ? $current->format('Y-m-d') : $current;
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';
  $text = fn ($v) => ucwords(str_replace('_', ' ', (string) $v));

  $contacts = old('contact_persons', ($principal?->contactPersons ?? collect())->map(fn ($c) => $c->toArray())->all());
  $contacts[] = [];

  $addresses = old('addresses', $principal?->addressRows() ?? []) ?: [['address_type' => 'Head Office']];
  $fees = old('manning_fees', $principal?->feeRows() ?? []) ?: [['currency' => 'IDR']];
  $currencyList = collect($currencies)->merge(collect($fees)->pluck('currency'))->push('IDR')->filter()->unique()->values();
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
  <a href="{{ route('principals.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    <button type="button" data-tab-target="basic-info" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Basic Info</button>
    <button type="button" data-tab-target="address-and-contact" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Address &amp; Contact</button>
    <button type="button" data-tab-target="business-terms" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Business Terms</button>
    <button type="button" data-tab-target="compliance-and-financial" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Compliance &amp; Financial</button>
    <button type="button" data-tab-target="contact-persons" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Contact Persons</button>
    <button type="button" data-tab-target="notes" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Notes</button>
  </div>
<div data-tab-panel="basic-info" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Principal Name *</label>
      <input type="text" name="principal_name" required value="{{ $val('principal_name') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Legal Entity Name</label>
      <input type="text" name="legal_entity_name" value="{{ $val('legal_entity_name') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Type *</label>
      <select name="principal_type" required class="{{ $input }}">
        @foreach($types as $type)<option value="{{ $type }}" @selected($val('principal_type', 'shipowner') === $type)>{{ $text($type) }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Status *</label>
      <select name="status" required class="{{ $input }}">
        @foreach($statuses as $status)<option value="{{ $status }}" @selected($val('status', 'active') === $status)>{{ $text($status) }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Country</label>
      <input type="text" name="country" value="{{ $val('country', 'Indonesia') }}" class="{{ $input }}" list="country-list">
      <datalist id="country-list">
        @foreach($countries as $country)<option value="{{ $country }}">@endforeach
      </datalist>
    </div>
  </div>
</div>
</div>

<div data-tab-panel="address-and-contact" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4" data-repeater="addresses">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-[11px] text-muted">Satu baris per kantor. Baris pertama dipakai sebagai alamat utama.</p>
    <button type="button" data-repeater-add class="text-xs text-brand hover:text-brand-d font-medium">+ Add address</button>
  </div>

  <div data-repeater-rows class="space-y-3">
    @foreach($addresses as $i => $address)
      <div data-repeater-row class="border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Address</span>
          <button type="button" data-repeater-remove class="text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Type</label>
            <select name="addresses[{{ $i }}][address_type]" class="{{ $input }}">
              @foreach($addressTypes as $type)<option value="{{ $type }}" @selected(($address['address_type'] ?? 'Head Office') === $type)>{{ $type }}</option>@endforeach
            </select>
          </div>
          <div class="md:col-span-9 min-w-0">
            <label class="{{ $label }}">Address</label>
            <textarea name="addresses[{{ $i }}][address]" rows="2" class="{{ $input }}">{{ $address['address'] ?? '' }}</textarea>
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">City</label>
            <input type="text" name="addresses[{{ $i }}][city]" value="{{ $address['city'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Province</label>
            <input type="text" name="addresses[{{ $i }}][province]" value="{{ $address['province'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Postal Code</label>
            <input type="text" name="addresses[{{ $i }}][postal_code]" value="{{ $address['postal_code'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Country</label>
            <input type="text" name="addresses[{{ $i }}][country]" value="{{ $address['country'] ?? '' }}" class="{{ $input }}" list="country-list">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Phone</label>
            <input type="text" name="addresses[{{ $i }}][phone]" value="{{ $address['phone'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Fax</label>
            <input type="text" name="addresses[{{ $i }}][fax]" value="{{ $address['fax'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">Email</label>
            <input type="email" name="addresses[{{ $i }}][email]" value="{{ $address['email'] ?? '' }}" class="{{ $input }}">
          </div>
          <div class="md:col-span-3 min-w-0">
            <label class="{{ $label }}">WeChat</label>
            <input type="text" name="addresses[{{ $i }}][wechat]" value="{{ $address['wechat'] ?? '' }}" class="{{ $input }}">
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-4 pt-2">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Website</label>
      <input type="text" name="website" value="{{ $val('website') }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

<div data-tab-panel="business-terms" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Contract Start</label>
      <input type="date" name="contract_start_date" value="{{ $val('contract_start_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Contract End</label>
      <input type="date" name="contract_end_date" value="{{ $val('contract_end_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Contract Type</label>
      <select name="contract_type" class="{{ $input }}">
        <option value="">—</option>
        @foreach($contractTypes as $type)<option value="{{ $type }}" @selected($val('contract_type') === $type)>{{ $text($type) }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Payment Terms</label>
      <input type="text" name="payment_terms" value="{{ $val('payment_terms') }}" placeholder="NET 30" class="{{ $input }}">
    </div>
  </div>
</div>

<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4" data-repeater="manning_fees">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h3 class="text-sm font-semibold text-slate-900">Manning Fee</h3>
    <button type="button" data-repeater-add class="text-xs text-brand hover:text-brand-d font-medium">+ Add fee</button>
  </div>

  <div data-repeater-rows class="space-y-3">
    @foreach($fees as $i => $fee)
      <div data-repeater-row class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end border border-line rounded-lg p-4">
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Manning Fee Type</label>
          <select name="manning_fees[{{ $i }}][fee_type]" class="{{ $input }}">
            <option value="">—</option>
            @foreach($feeTypes as $type)<option value="{{ $type }}" @selected(($fee['fee_type'] ?? null) === $type)>{{ $text($type) }}</option>@endforeach
          </select>
        </div>
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Amount</label>
          <input type="number" step="0.01" min="0" name="manning_fees[{{ $i }}][amount]" value="{{ $fee['amount'] ?? '' }}" class="{{ $input }} tabular-nums">
        </div>
        <div class="md:col-span-2 min-w-0">
          <label class="{{ $label }}">Currency</label>
          <select name="manning_fees[{{ $i }}][currency]" class="{{ $input }}">
            @foreach($currencyList as $currency)<option value="{{ $currency }}" @selected(($fee['currency'] ?? 'IDR') === $currency)>{{ $currency }}</option>@endforeach
          </select>
        </div>
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Description</label>
          <input type="text" name="manning_fees[{{ $i }}][description]" value="{{ $fee['description'] ?? '' }}" placeholder="Officers, ratings…" class="{{ $input }}">
        </div>
        <div class="md:col-span-1 min-w-0 text-right pb-2">
          <button type="button" data-repeater-remove class="text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>
      </div>
    @endforeach
  </div>
</div>
</div>

<div data-tab-panel="compliance-and-financial" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">P&amp;I Club</label>
      <input type="text" name="p_and_i_club" value="{{ $val('p_and_i_club') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Wage Scale Reference</label>
      <input type="text" name="wage_scale_reference" value="{{ $val('wage_scale_reference') }}" placeholder="ITF TCC" class="{{ $input }}">
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Billing Address</label>
      <textarea name="billing_address" rows="2" class="{{ $input }}">{{ $val('billing_address') }}</textarea>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Tax ID (NPWP)</label>
      <input type="text" name="tax_id" value="{{ $val('tax_id') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Bank Details</label>
      <textarea name="bank_details" rows="2" class="{{ $input }}">{{ $val('bank_details') }}</textarea>
    </div>
  </div>
</div>
</div>

<div data-tab-panel="contact-persons" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-4">
  <div class="flex items-center justify-end">
    <button type="button" id="add-contact" class="text-xs text-brand hover:text-brand-d font-medium">+ Add row</button>
  </div>
  <p class="text-[11px] text-muted">Minimal satu contact person, dan tandai satu sebagai primary.</p>

  <div id="contact-rows" class="space-y-3">
    @foreach($contacts as $i => $contact)
      <div class="contact-row border border-line rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[11px] uppercase tracking-wider text-muted font-semibold">Contact</span>
          <button type="button" class="remove-contact text-xs text-rose-500 hover:text-rose-600">Remove</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Name</label>
          <input type="text" name="contact_persons[{{ $i }}][name]" value="{{ $contact['name'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Position</label>
          <input type="text" name="contact_persons[{{ $i }}][position]" value="{{ $contact['position'] ?? '' }}" placeholder="Crew Superintendent" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Email</label>
          <input type="email" name="contact_persons[{{ $i }}][email]" value="{{ $contact['email'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">Phone</label>
          <input type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $contact['phone'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">WhatsApp</label>
          <input type="text" name="contact_persons[{{ $i }}][whatsapp]" value="{{ $contact['whatsapp'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0">
          <label class="{{ $label }}">WeChat</label>
          <input type="text" name="contact_persons[{{ $i }}][wechat]" value="{{ $contact['wechat'] ?? '' }}" class="{{ $input }}">
        </div>
        <div class="md:col-span-4 min-w-0 flex items-center gap-2 pt-5">
          <input type="hidden" name="contact_persons[{{ $i }}][is_primary]" value="0">
          <input type="checkbox" name="contact_persons[{{ $i }}][is_primary]" value="1" @checked(!empty($contact['is_primary'])) class="primary-flag">
          <span class="text-xs text-slate-700">Primary</span>
        </div>
        <div class="md:col-span-12 min-w-0">
          <label class="{{ $label }}">Notes</label>
          <input type="text" name="contact_persons[{{ $i }}][notes]" value="{{ $contact['notes'] ?? '' }}" class="{{ $input }}">
        </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
</div>

<div data-tab-panel="notes" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-5">
  <textarea name="notes" rows="3" class="{{ $input }}">{{ $val('notes') }}</textarea>
</div>
</div>

<script>
  (function () {
    var list = document.getElementById('contact-rows');

    document.getElementById('add-contact').addEventListener('click', function () {
      var rows = list.querySelectorAll('.contact-row');
      var clone = rows[rows.length - 1].cloneNode(true);
      var index = rows.length;

      clone.querySelectorAll('input').forEach(function (field) {
        field.name = field.name.replace(/contact_persons\[\d+]/, 'contact_persons[' + index + ']');
        if (field.type === 'checkbox') { field.checked = false; }
        else if (field.type === 'hidden') { field.value = '0'; }
        else { field.value = ''; }
      });

      list.appendChild(clone);
    });

    list.addEventListener('click', function (event) {
      if (! event.target.classList.contains('remove-contact')) { return; }
      if (list.querySelectorAll('.contact-row').length === 1) { return; }
      event.target.closest('.contact-row').remove();
    });

    // Primary is a single choice, not a free-for-all.
    list.addEventListener('change', function (event) {
      if (! event.target.classList.contains('primary-flag') || ! event.target.checked) { return; }
      list.querySelectorAll('.primary-flag').forEach(function (box) {
        if (box !== event.target) { box.checked = false; }
      });
    });
  })();
</script>

</div>