@extends('layouts.app')

@section('title', $application->application_code . ' · HPYMarine')
@section('heading', 'Recruitment Pipeline')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $fmt = fn ($d) => $d ? $d->format('d M Y') : '—';
    $current = array_search($application->stage, $stages, true);
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <a href="{{ route('applications.index') }}" class="text-xs text-muted hover:text-slate-900">← Back to Pipeline</a>
      <h1 class="text-2xl font-semibold text-slate-900 mt-2">{{ $application->candidate?->full_name ?? '—' }}</h1>
      <p class="text-sm text-muted mt-1">
        <span class="font-mono">{{ $application->application_code }}</span> ·
        {{ $application->applied_rank ?: 'Rank belum ditentukan' }}
        {{ $application->vessel ? ' · ' . $application->vessel : '' }}
      </p>
    </div>
    <div class="flex items-center gap-2">
      @can('applications.update')
        <a href="{{ route('applications.edit', $application) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Edit</a>
      @endcan
      @if($application->candidate)
        <a href="{{ route('candidates.show', $application->candidate) }}" class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Lihat Kandidat</a>
      @endif
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  {{-- Stage rail --}}
  <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <div class="flex items-center gap-2 overflow-x-auto">
      @foreach($stages as $i => $stage)
        @php
          $done = $current !== false && $i < $current;
          $here = $application->stage === $stage;
        @endphp
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-2 px-3 py-1.5 rounded-full text-[12px] border
                      {{ $here ? 'bg-brand text-white border-brand font-medium' : ($done ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-muted border-line') }}">
            {{ $done ? '✓' : $i + 1 }} {{ $text($stage) }}
          </div>
          @if(! $loop->last)<span class="text-line">—</span>@endif
        </div>
      @endforeach
    </div>

    @if(in_array($application->stage, \App\Models\CrewApplication::CLOSED_STAGES, true))
      <div class="mt-4 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-4 py-2">
        Lamaran ditutup: {{ $text($application->stage) }}{{ $application->rejection_reason ? ' — ' . $application->rejection_reason : '' }}
      </div>
    @elseif($application->is_open)
      @can('applications.update')
        <div class="mt-5 flex flex-wrap items-end gap-3 border-t border-line pt-4">
          <form method="POST" action="{{ route('applications.advance', $application) }}" class="flex items-end gap-2">
            @csrf
            @if($application->next_stage === 'interview')
              <div>
                <label class="block text-xs text-muted mb-1">Nilai Interview</label>
                <input type="number" name="interview_score" min="1" max="10" class="w-24 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
            @endif
            @if($application->next_stage === 'mcu')
              <div>
                <label class="block text-xs text-muted mb-1">Hasil MCU</label>
                <select name="mcu_result" class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
                  @foreach(\App\Models\CrewApplication::MCU_RESULTS as $result)
                    <option value="{{ $result }}">{{ $text($result) }}</option>
                  @endforeach
                </select>
              </div>
            @endif
            @if($application->next_stage === 'offer')
              <div>
                <label class="block text-xs text-muted mb-1">Gaji Ditawarkan</label>
                <input type="number" step="0.01" name="offered_salary" class="w-40 bg-white border border-line rounded-md py-1.5 px-2 text-sm">
              </div>
            @endif
            <button class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2">
              Lanjut ke {{ $text($application->next_stage) }}
            </button>
          </form>

          <form method="POST" action="{{ route('applications.close', $application) }}" class="flex items-end gap-2">
            @csrf
            <div>
              <label class="block text-xs text-muted mb-1">Tutup lamaran</label>
              <select name="stage" class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
                @foreach(\App\Models\CrewApplication::CLOSED_STAGES as $stage)
                  <option value="{{ $stage }}">{{ $text($stage) }}</option>
                @endforeach
              </select>
            </div>
            <input type="text" name="rejection_reason" placeholder="Alasan" class="bg-white border border-line rounded-md py-1.5 px-2 text-sm w-48">
            <button class="border border-line bg-white hover:bg-slate-50 text-slate-700 text-sm rounded-md px-4 py-2">Tutup</button>
          </form>
        </div>
        @if($application->next_stage === 'hired')
          <p class="text-[11px] text-muted mt-2">Menandai "hired" akan membuat Employee di ERP HPY dan membuka Crew Assignment.</p>
        @endif
      @endcan
    @endif
  </div>

  @if($application->assignment)
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
      Sudah dibuatkan penugasan
      <a href="{{ route('assignments.show', $application->assignment) }}" class="underline font-medium">{{ $application->assignment->assignment_code }}</a>
      ({{ $text($application->assignment->status) }}).
    </div>
  @endif

  @php
    $rows = [
      'Kandidat' => $application->candidate?->candidate_code,
      'Applied Rank' => $application->applied_rank,
      'Kapal' => $application->vessel,
      'Principal' => $application->principal,
      'Tanggal Lamaran' => $fmt($application->applied_date),
      'Lama di Pipeline' => $application->days_in_pipeline . ' hari',
      'Screening' => $fmt($application->screening_date),
      'Interview' => $fmt($application->interview_date),
      'Nilai Interview' => $application->interview_score,
      'MCU' => $fmt($application->mcu_date),
      'Hasil MCU' => $text($application->mcu_result),
      'Offer' => $fmt($application->offer_date),
      'Gaji Ditawarkan' => $application->offered_salary ? $application->offered_salary_currency . ' ' . number_format((float) $application->offered_salary, 2) : null,
      'Keputusan' => $fmt($application->decision_date),
    ];
  @endphp

  <div class="bg-panel border border-line rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">Detail</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-5 gap-x-8 text-sm">
      @foreach($rows as $labelText => $value)
        <div>
          <div class="text-[11px] uppercase tracking-wider text-muted">{{ $labelText }}</div>
          <div class="text-slate-800 mt-1 font-medium">{{ filled($value) ? $value : '—' }}</div>
        </div>
      @endforeach
    </div>
  </div>

  @if($application->interview_notes || $application->notes)
    <div class="bg-panel border border-line rounded-xl p-6 shadow-sm space-y-3">
      @if($application->interview_notes)
        <div>
          <div class="text-[11px] uppercase tracking-wider text-muted">Catatan Interview</div>
          <p class="text-sm text-slate-700 whitespace-pre-line mt-1">{{ $application->interview_notes }}</p>
        </div>
      @endif
      @if($application->notes)
        <div>
          <div class="text-[11px] uppercase tracking-wider text-muted">Catatan</div>
          <p class="text-sm text-slate-700 whitespace-pre-line mt-1">{{ $application->notes }}</p>
        </div>
      @endif
    </div>
  @endif
@endsection
