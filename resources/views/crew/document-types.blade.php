@extends('layouts.app')

@section('title', 'Document Types · HPYMarine')
@section('heading', 'Documents')

@section('content')
  @php
    $input = 'w-full bg-white border border-line rounded-md py-2 px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
    $label = 'block text-xs text-muted mb-1';
  @endphp

  <div>
    <h1 class="text-2xl font-semibold text-slate-900">Document Types</h1>
    <p class="text-sm text-muted mt-1">Pilihan <span class="font-medium text-slate-700">Type</span> di Certificates &amp; Documents Crew Master. Disimpan di master ERP HPY "Crew Certificate Type".</p>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">
      @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
  @endif

  @if(! $editable)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
      Master "Crew Certificate Type" belum ada di ERP HPY, jadi pilihannya masih daftar bawaan
      ({{ count($shipped) }} tipe) dan belum bisa ditambah. Jalankan <code class="font-mono text-xs">php artisan erp:sync-crew-fields</code> sekali.
    </div>
  @else
    <form method="POST" action="{{ route('crew.document-types.store') }}" class="bg-panel border border-line rounded-xl p-6 shadow-sm">
      @csrf
      <h2 class="text-sm font-semibold text-slate-900 mb-4">Tambah document type</h2>
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
        <div class="md:col-span-5 min-w-0">
          <label class="{{ $label }}">Name *</label>
          <input type="text" name="certificate_name" required maxlength="140" value="{{ old('certificate_name') }}" placeholder="Contoh: Tanker Familiarization" class="{{ $input }}">
        </div>
        <div class="md:col-span-3 min-w-0">
          <label class="{{ $label }}">Category</label>
          <select name="category" class="{{ $input }}">
            <option value="">—</option>
            @foreach($categories as $c)<option value="{{ $c }}" @selected(old('category') === $c)>{{ $c }}</option>@endforeach
          </select>
        </div>
        <div class="md:col-span-2 min-w-0">
          <label class="{{ $label }}">Validity (months)</label>
          <input type="number" name="validity_months" min="0" max="600" value="{{ old('validity_months') }}" class="{{ $input }} tabular-nums">
        </div>
        <div class="md:col-span-2 min-w-0">
          <button class="w-full bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add</button>
        </div>
      </div>
    </form>
  @endif

  <div class="bg-panel border border-line rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Document Type</th>
            <th class="text-left px-3 py-3 font-medium">Category</th>
            <th class="text-left px-3 py-3 font-medium">Validity (months)</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
          @if($editable)
            @forelse($rows as $row)
              @php $formId = 'type-' . $loop->index; @endphp
              <tr class="border-t border-line {{ $row['is_active'] ? '' : 'opacity-60' }}">
                <td class="px-5 py-2.5 text-slate-900 font-medium">{{ $row['name'] }}</td>
                <td class="px-3 py-2.5">
                  <select name="category" form="{{ $formId }}" class="{{ $input }} py-1.5 min-w-36">
                    <option value="">—</option>
                    @foreach($categories as $c)<option value="{{ $c }}" @selected(($row['category'] ?? '') === $c)>{{ $c }}</option>@endforeach
                  </select>
                </td>
                <td class="px-3 py-2.5">
                  <input type="number" name="validity_months" form="{{ $formId }}" min="0" max="600" value="{{ $row['validity_months'] ?: '' }}" class="{{ $input }} py-1.5 w-24 tabular-nums">
                </td>
                <td class="px-3 py-2.5">
                  <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                    <input type="hidden" name="is_active" value="0" form="{{ $formId }}">
                    <input type="checkbox" name="is_active" value="1" form="{{ $formId }}" @checked($row['is_active'])> Active
                  </label>
                </td>
                <td class="px-5 py-2.5 text-right">
                  <form id="{{ $formId }}" method="POST" action="{{ route('crew.document-types.update', $row['name']) }}">
                    @csrf @method('PATCH')
                    <button class="text-xs text-brand hover:text-brand-d font-medium">Save</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="px-5 py-10 text-center text-muted">Belum ada document type.</td></tr>
            @endforelse
          @else
            @foreach($shipped as $name)
              <tr class="border-t border-line">
                <td class="px-5 py-2.5 text-slate-900 font-medium">{{ $name }}</td>
                <td class="px-3 py-2.5 text-muted" colspan="4">Daftar bawaan</td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
@endsection
