@php
  /** @var \App\Models\CrewApplication|null $application */
  $application = $application ?? null;
  $preselected = $preselected ?? null;

  $val = function ($field, $default = '') use ($application) {
      $current = old($field, $application->{$field} ?? $default);

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
<div class="sticky top-0 z-20 -mx-6 px-6 py-3 bg-canvas/95 backdrop-blur border-b border-line flex items-center gap-3">
  <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-5 py-2 shadow-sm">{{ $submitLabel }}</button>
  <a href="{{ route('applications.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</a>
</div>

<div data-tab-group>
  <div class="flex flex-wrap items-center gap-1 border-b border-line mb-5">
    <button type="button" data-tab-target="lamaran" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Lamaran</button>
    <button type="button" data-tab-target="progres-tahapan" class="px-4 py-2 text-sm border-b-2 -mb-px border-transparent text-muted hover:text-slate-900">Progres Tahapan</button>
  </div>
<div data-tab-panel="lamaran" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Kandidat *</label>
      <select name="crew_candidate_id" required class="{{ $input }}">
        <option value="">— pilih kandidat —</option>
        @foreach($candidates as $candidate)
          <option value="{{ $candidate->id }}"
            @selected((string) $val('crew_candidate_id', $preselected) === (string) $candidate->id)>
            {{ $candidate->candidate_code }} — {{ $candidate->full_name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Applied Rank</label>
      <select name="applied_rank" class="{{ $input }}">
        <option value="">—</option>
        @foreach($ranks as $rank)<option value="{{ $rank }}" @selected($val('applied_rank') === $rank)>{{ $rank }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Kapal Tujuan</label>
      <select name="vessel" class="{{ $input }}">
        <option value="">—</option>
        @foreach($vessels as $vessel)<option value="{{ $vessel }}" @selected($val('vessel') === $vessel)>{{ $vessel }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Principal</label>
      <input type="text" name="principal" value="{{ $val('principal') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Tahap *</label>
      <select name="stage" class="{{ $input }}">
        @foreach($stages as $stage)<option value="{{ $stage }}" @selected($val('stage', 'applied') === $stage)>{{ $text($stage) }}</option>@endforeach
      </select>
    </div>

    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Tanggal Lamaran *</label>
      <input type="date" name="applied_date" required value="{{ $val('applied_date', now()->format('Y-m-d')) }}" class="{{ $input }}">
    </div>
  </div>
</div>
</div>

<div data-tab-panel="progres-tahapan" class="space-y-5">
<div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Screening</label>
      <input type="date" name="screening_date" value="{{ $val('screening_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Interview</label>
      <input type="date" name="interview_date" value="{{ $val('interview_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Nilai Interview (1-10)</label>
      <input type="number" min="1" max="10" name="interview_score" value="{{ $val('interview_score') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">MCU</label>
      <input type="date" name="mcu_date" value="{{ $val('mcu_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Hasil MCU</label>
      <select name="mcu_result" class="{{ $input }}">
        <option value="">—</option>
        @foreach($mcuResults as $result)<option value="{{ $result }}" @selected($val('mcu_result') === $result)>{{ $text($result) }}</option>@endforeach
      </select>
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Tanggal Offer</label>
      <input type="date" name="offer_date" value="{{ $val('offer_date') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Gaji Ditawarkan</label>
      <input type="number" step="0.01" name="offered_salary" value="{{ $val('offered_salary') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-3 min-w-0">
      <label class="{{ $label }}">Tanggal Keputusan</label>
      <input type="date" name="decision_date" value="{{ $val('decision_date') }}" class="{{ $input }}">
    </div>

    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Catatan Interview</label>
      <textarea name="interview_notes" rows="2" class="{{ $input }}">{{ $val('interview_notes') }}</textarea>
    </div>
    <div class="md:col-span-6 min-w-0">
      <label class="{{ $label }}">Alasan Ditolak</label>
      <input type="text" name="rejection_reason" value="{{ $val('rejection_reason') }}" class="{{ $input }}">
    </div>
    <div class="md:col-span-12 min-w-0">
      <label class="{{ $label }}">Catatan</label>
      <textarea name="notes" rows="3" class="{{ $input }}">{{ $val('notes') }}</textarea>
    </div>
  </div>
</div>
</div>


</div>