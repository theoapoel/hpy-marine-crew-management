@extends('layouts.app')

@section('title', 'Candidate Pool · HPYMarine')
@section('heading', 'Candidate Pool')

@section('content')
  @php
    $statusColors = [
      'applicant'  => 'bg-blue-50 text-blue-700 border-blue-200',
      'in_process' => 'bg-amber-50 text-amber-700 border-amber-200',
      'employed'   => 'bg-green-50 text-green-700 border-green-200',
      'on_leave'   => 'bg-slate-100 text-slate-600 border-slate-200',
      'blacklist'  => 'bg-rose-50 text-rose-700 border-rose-200',
      'retired'    => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
    $label = fn ($value) => ucwords(str_replace('_', ' ', (string) $value));
    $sortLink = function ($column, $text) use ($sort, $direction) {
        $next = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
        $arrow = $sort === $column ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
        return '<a class="hover:text-slate-900" href="' . request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next]) . '">' . $text . $arrow . '</a>';
    };
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Candidate Pool</h1>
      <p class="text-sm text-muted mt-1">{{ $candidates->total() }} kandidat pelaut</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('candidates.export', request()->query()) }}"
         class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Export CSV</a>
      @can('candidates.create')
        <a href="{{ route('candidates.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add Candidate</a>
      @endcan
    </div>
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  {{-- Filters: submitted on change, so it behaves like a live filter --}}
  <form method="GET" class="flex flex-wrap gap-2" id="filters">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama, seaman book, HP, NIK..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand w-72">

    <select name="status" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua status</option>
      @foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $label($s) }}</option>@endforeach
    </select>

    <select name="source" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua source</option>
      @foreach($sources as $s)<option value="{{ $s }}" @selected(($filters['source'] ?? '') === $s)>{{ $label($s) }}</option>@endforeach
    </select>

    <select name="applied_rank" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua rank</option>
      @foreach($ranks as $r)<option value="{{ $r }}" @selected(($filters['applied_rank'] ?? '') === $r)>{{ $r }}</option>@endforeach
    </select>

    <select name="coc_type" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua COC</option>
      @foreach($cocTypes as $c)<option value="{{ $c }}" @selected(($filters['coc_type'] ?? '') === $c)>{{ $c }}</option>@endforeach
    </select>

    <select name="availability" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">Semua ketersediaan</option>
      <option value="available" @selected(($filters['availability'] ?? '') === 'available')>Available</option>
      <option value="unavailable" @selected(($filters['availability'] ?? '') === 'unavailable')>Tidak available</option>
    </select>

    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
    @if(array_filter($filters))
      <a href="{{ route('candidates.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Reset</a>
    @endif
  </form>

  {{-- Bulk actions, enabled once rows are ticked --}}
  <form method="POST" action="{{ route('candidates.bulk-status') }}" id="bulk">
    @csrf
    <div id="bulk-bar" class="hidden items-center gap-2 bg-brand/5 border border-brand/20 rounded-lg px-4 py-2 text-sm mb-3">
      <span class="text-slate-700"><span id="bulk-count">0</span> dipilih</span>
      <select name="status" required class="bg-white border border-line rounded-md py-1.5 px-2 text-sm">
        <option value="">Ubah status ke...</option>
        @foreach($statuses as $s)<option value="{{ $s }}">{{ $label($s) }}</option>@endforeach
      </select>
      <button class="bg-brand hover:bg-brand-d text-white rounded-md px-3 py-1.5">Terapkan</button>
      <button type="button" id="bulk-export" class="border border-line bg-white rounded-md px-3 py-1.5">Export terpilih</button>
    </div>

    <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
              <th class="px-4 py-3 w-8"><input type="checkbox" id="check-all"></th>
              <th class="text-left px-3 py-3 font-medium">Code</th>
              <th class="text-left px-3 py-3 font-medium">{!! $sortLink('full_name', 'Nama') !!}</th>
              <th class="text-left px-3 py-3 font-medium">Applied Rank</th>
              <th class="text-left px-3 py-3 font-medium">COC</th>
              <th class="text-left px-3 py-3 font-medium">Status</th>
              <th class="text-left px-3 py-3 font-medium">Source</th>
              <th class="text-left px-3 py-3 font-medium">{!! $sortLink('availability_date', 'Available') !!}</th>
              <th class="text-right px-5 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($candidates as $candidate)
              <tr class="border-t border-line hover:bg-slate-50">
                <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $candidate->id }}" class="row-check"></td>
                <td class="px-3 py-3 text-[12px] text-muted font-mono">{{ $candidate->candidate_code }}</td>
                <td class="px-3 py-3">
                  <div class="flex items-center gap-3">
                    @if($candidate->photo_path)
                      <img src="{{ Storage::url($candidate->photo_path) }}" alt="{{ $candidate->full_name }}"
                           class="w-8 h-8 rounded-full object-cover border border-line shrink-0">
                    @else
                      <div class="w-8 h-8 rounded-full bg-slate-100 text-muted flex items-center justify-center text-[11px] font-semibold shrink-0">
                        {{ mb_strtoupper(mb_substr($candidate->full_name, 0, 1)) }}
                      </div>
                    @endif
                    <div>
                      <a href="{{ route('candidates.show', $candidate) }}" class="text-slate-900 font-medium hover:text-brand">{{ $candidate->full_name }}</a>
                      <div class="text-[11px] text-muted">{{ $candidate->seaman_book_no ?: '—' }} · {{ $candidate->phone }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3 text-slate-600">{{ $candidate->applied_rank ?: '—' }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $candidate->coc_type }}</td>
                <td class="px-3 py-3"><span class="chip border {{ $statusColors[$candidate->status] ?? '' }}">{{ $label($candidate->status) }}</span></td>
                <td class="px-3 py-3 text-slate-600">{{ $label($candidate->source) }}</td>
                <td class="px-3 py-3 text-slate-600">{{ $candidate->availability_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                  <a href="{{ route('candidates.show', $candidate) }}" class="text-slate-600 hover:text-slate-900">View</a>
                  @can('candidates.update')
                    <a href="{{ route('candidates.edit', $candidate) }}" class="text-brand hover:text-brand-d font-medium ml-3">Edit</a>
                  @endcan
                  @can('candidates.delete')
                    <button type="button" class="text-rose-500 hover:text-rose-600 ml-3 delete-candidate"
                            data-name="{{ $candidate->full_name }}" data-url="{{ route('candidates.destroy', $candidate) }}">Delete</button>
                  @endcan
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="px-5 py-10 text-center text-muted">Belum ada kandidat.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <div>{{ $candidates->links() }}</div>

  {{-- Delete confirmation --}}
  <div id="delete-modal" class="hidden fixed inset-0 bg-slate-900/40 items-center justify-center p-6 z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
      <h2 class="text-base font-semibold text-slate-900">Hapus kandidat?</h2>
      <p class="text-sm text-muted mt-2"><span id="delete-name" class="font-medium text-slate-700"></span> akan dihapus dari Candidate Pool.</p>
      <form method="POST" id="delete-form" class="mt-5 flex justify-end gap-2">
        @csrf @method('DELETE')
        <button type="button" id="delete-cancel" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Batal</button>
        <button class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md px-4 py-2">Hapus</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      // Filters behave like live search: changing one submits the form.
      document.querySelectorAll('#filters select').forEach(function (select) {
        select.addEventListener('change', function () { document.getElementById('filters').submit(); });
      });

      var searchBox = document.querySelector('#filters input[name="search"]');
      var timer;
      searchBox.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { document.getElementById('filters').submit(); }, 500);
      });

      // Bulk selection
      var bar = document.getElementById('bulk-bar');
      var count = document.getElementById('bulk-count');
      var rows = function () { return Array.from(document.querySelectorAll('.row-check')); };

      function refresh() {
        var selected = rows().filter(function (r) { return r.checked; });
        count.textContent = selected.length;
        bar.classList.toggle('hidden', selected.length === 0);
        bar.classList.toggle('flex', selected.length > 0);
      }

      document.getElementById('check-all').addEventListener('change', function (e) {
        rows().forEach(function (r) { r.checked = e.target.checked; });
        refresh();
      });
      rows().forEach(function (r) { r.addEventListener('change', refresh); });

      document.getElementById('bulk-export').addEventListener('click', function () {
        var ids = rows().filter(function (r) { return r.checked; }).map(function (r) { return 'ids[]=' + r.value; });
        window.location = '{{ route('candidates.export') }}?' + ids.join('&');
      });

      // Delete confirmation
      var modal = document.getElementById('delete-modal');
      document.querySelectorAll('.delete-candidate').forEach(function (button) {
        button.addEventListener('click', function () {
          document.getElementById('delete-name').textContent = button.dataset.name;
          document.getElementById('delete-form').action = button.dataset.url;
          modal.classList.remove('hidden');
          modal.classList.add('flex');
        });
      });
      document.getElementById('delete-cancel').addEventListener('click', function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      });
    })();
  </script>
@endsection
