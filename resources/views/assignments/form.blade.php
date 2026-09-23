@php
  /** @var \App\Models\CrewAssignment|null $assignment */
  $assignment = $assignment ?? null;

  $val = function ($field, $default = '') use ($assignment) {
      $current = old($field, $assignment->{$field} ?? $default);

      return $current instanceof \Illuminate\Support\Carbon ? $current->format('Y-m-d') : $current;
  };

  $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
  $label = 'block text-xs text-muted mb-1 leading-4 min-h-[1rem]';
  $text = fn ($v) => ucwords(str_replace('_', ' ', (string) $v));
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
  <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-5 py-2 shadow-sm">{{ $submitLabel }}</button>
  <a href="{{ route('assignments.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    <button type="button" data-tab-target="penugasan" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Assignment</button>
    <button type="button" data-tab-target="sign-on-sign-off" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Sign On / Sign Off</button>
  </div>
<div data-tab-panel="penugasan" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Crew Name *</label>
      <input type="text" name="crew_name" required value="{{ $val('crew_name') }}" class="{{ $input }}" list="crew-names">
      <datalist id="crew-names">
        @foreach($candidates as $candidate)<option value="{{ $candidate->full_name }}">@endforeach
        @foreach($crews as $crew)<option value="{{ $crew['name'] }}">@endforeach
      </datalist>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Candidate</label>
      <select name="crew_candidate_id" data-fill class="{{ $input }}">
        <option value="">— Auto-match by name —</option>
        @foreach($candidates as $candidate)
          <option value="{{ $candidate->id }}" data-name="{{ $candidate->full_name }}" data-employee="{{ $candidate->linked_employee_id }}" data-rank="{{ $candidate->applied_rank }}"
                  @selected((string) $val('crew_candidate_id') === (string) $candidate->id)>
            {{ $candidate->candidate_code }} — {{ $candidate->full_name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Employee (ERP HPY)</label>
      @php $employeeId = (string) $val('employee_id'); @endphp
      <select name="employee_id" data-fill class="{{ $input }}">
        <option value="">— Auto-match by name —</option>
        @if($employeeId !== '' && ! collect($crews)->contains('id', $employeeId))
          <option value="{{ $employeeId }}" selected>{{ $employeeId }}</option>
        @endif
        @foreach($crews as $crew)
          <option value="{{ $crew['id'] }}" data-name="{{ $crew['name'] }}" data-rank="{{ $crew['rank'] }}" @selected($employeeId === $crew['id'])>
            {{ $crew['name'] }} — {{ $crew['id'] }}
          </option>
        @endforeach
      </select>
    </div>

    <p class="md:col-span-12 -mt-2 text-[11px] text-muted">
      Leave Candidate / Employee empty to link them by name on save. Without an Employee, changes do not reach Crew Master in ERP HPY.
    </p>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Vessel</label>
      <select name="vessel" class="{{ $input }}">
        <option value="">—</option>
        @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected($val('vessel') === $vessel)>{{ $vessel }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Rank</label>
      <select name="rank" class="{{ $input }}">
        <option value="">—</option>
        @foreach($ranks as $rank)<option value="{{ $rank }}" @selected($val('rank') === $rank)>{{ $rank }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Status *</label>
      <select name="status" class="{{ $input }}">
        @foreach($statuses as $status)<option value="{{ $status }}" @selected($val('status', 'planned') === $status)>{{ $text($status) }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Wage</label>
      <div class="flex">
        <select name="wage_currency" aria-label="Wage currency"
                class="bg-slate-50 border border-line border-r-0 rounded-l-md py-2 pl-2 pr-7 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
          @foreach(collect($currencies)->push($val('wage_currency', 'IDR'))->unique() as $currency)
            <option value="{{ $currency }}" @selected($val('wage_currency', 'IDR') === $currency)>{{ $currency }}</option>
          @endforeach
        </select>
        <input type="number" step="0.01" min="0" name="wage" value="{{ $val('wage') }}" inputmode="decimal"
               class="{{ $input }} rounded-l-none tabular-nums min-w-0">
      </div>
    </div>
  </div>
</div>
</div>

<div data-tab-panel="sign-on-sign-off" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm" data-contract>
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Planned Sign On</label>
      <input type="date" name="planned_sign_on_date" data-contract-planned value="{{ $val('planned_sign_on_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign On</label>
      <input type="date" name="sign_on_date" data-contract-start value="{{ $val('sign_on_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign On Port</label>
      <input type="text" name="sign_on_port" value="{{ $val('sign_on_port') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Contract (months)</label>
      <input type="number" name="contract_months" data-contract-months min="1" max="36" value="{{ $val('contract_months') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Planned Sign Off</label>
      <input type="date" name="planned_sign_off_date" data-contract-end value="{{ $val('planned_sign_off_date') }}" class="{{ $input }}">
      <p data-contract-hint class="text-[11px] text-muted mt-1" aria-live="polite"></p>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign Off</label>
      <input type="date" name="sign_off_date" value="{{ $val('sign_off_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign Off Port</label>
      <input type="text" name="sign_off_port" value="{{ $val('sign_off_port') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Sign Off Reason</label>
      <input type="text" name="sign_off_reason" value="{{ $val('sign_off_reason') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-12 min-w-0">
      <label class="{{ $label }}">Notes</label>
      <textarea name="notes" rows="3" class="{{ $input }}">{{ $val('notes') }}</textarea>
    </div>
  </div>
</div>
</div>


</div>