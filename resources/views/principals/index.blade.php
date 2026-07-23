@extends('layouts.app')

@section('title', 'Principals · HPYMarine')
@section('heading', 'Principals')

@section('content')
  @php
    $text = fn ($v) => filled($v) ? ucwords(str_replace('_', ' ', (string) $v)) : '—';
    $statusColors = [
      'prospect' => 'bg-slate-100 text-slate-600 border-slate-200',
      'active' => 'bg-green-50 text-green-700 border-green-200',
      'on_hold' => 'bg-amber-50 text-amber-700 border-amber-200',
      'terminated' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
  @endphp

  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Principals</h1>
      <p class="text-sm text-muted mt-1">{{ $principals->total() }} principals</p>
    </div>
    @can('principals.create')
      <a href="{{ route('principals.create') }}" class="bg-brand hover:bg-brand-d text-white text-sm font-medium rounded-md px-4 py-2 shadow-sm">+ Add Principal</a>
    @endcan
  </div>

  @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-600 text-sm rounded-lg px-4 py-3">{{ $errors->first() }}</div>
  @endif

  <form method="GET" class="flex flex-wrap gap-2" id="filters">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, code or email..."
           class="bg-white border border-line rounded-md py-2 px-3 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
    <select name="status" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">All statuses</option>
      @foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $text($status) }}</option>@endforeach
    </select>
    <select name="principal_type" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">All types</option>
      @foreach($types as $type)<option value="{{ $type }}" @selected(($filters['principal_type'] ?? '') === $type)>{{ $text($type) }}</option>@endforeach
    </select>
    <select name="country" class="bg-white border border-line rounded-md py-2 px-3 text-sm">
      <option value="">All countries</option>
      @foreach($countries as $country)<option value="{{ $country }}" @selected(($filters['country'] ?? '') === $country)>{{ $country }}</option>@endforeach
    </select>
    <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Filter</button>
    @if(array_filter($filters))
      <a href="{{ route('principals.index') }}" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Reset</a>
    @endif
  </form>

  <div class="bg-panel border border-line rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-muted tracking-wider bg-slate-50 border-b border-line">
            <th class="text-left px-5 py-3 font-medium">Code</th>
            <th class="text-left px-3 py-3 font-medium">Principal</th>
            <th class="text-left px-3 py-3 font-medium">Type</th>
            <th class="text-left px-3 py-3 font-medium">Country</th>
            <th class="text-left px-3 py-3 font-medium">Vessels</th>
            <th class="text-left px-3 py-3 font-medium">Status</th>
            <th class="text-left px-3 py-3 font-medium">ERP HPY</th>
            <th class="text-right px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($principals as $principal)
            <tr class="border-t border-line hover:bg-slate-50">
              <td class="px-5 py-3 font-mono text-[12px] text-muted">{{ $principal->principal_code }}</td>
              <td class="px-3 py-3">
                <a href="{{ route('principals.show', $principal) }}" class="text-slate-900 font-medium hover:text-brand">{{ $principal->principal_name }}</a>
                <div class="text-[11px] text-muted">{{ $principal->legal_entity_name ?: ($principal->email ?: '—') }}</div>
              </td>
              <td class="px-3 py-3 text-slate-600">{{ $text($principal->principal_type) }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $principal->country ?: '—' }}</td>
              <td class="px-3 py-3 text-slate-600">{{ $principal->vessel_count }}</td>
              <td class="px-3 py-3"><span class="chip border {{ $statusColors[$principal->status] ?? '' }}">{{ $text($principal->status) }}</span></td>
              <td class="px-3 py-3">
                <x-erpnext-sync-badge :model="$principal" />
              </td>
              <td class="px-5 py-3 text-right whitespace-nowrap">
                <a href="{{ route('principals.show', $principal) }}" class="text-slate-600 hover:text-slate-900">View</a>
                @can('principals.update')
                  <a href="{{ route('principals.edit', $principal) }}" class="text-brand hover:text-brand-d font-medium ml-3">Edit</a>
                @endcan
                @can('principals.delete')
                  <button type="button" class="text-rose-500 hover:text-rose-600 ml-3 delete-principal"
                          data-name="{{ $principal->principal_name }}"
                          data-vessels="{{ $principal->vessel_count }}"
                          data-url="{{ route('principals.destroy', $principal) }}">Delete</button>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">No principals yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $principals->links() }}</div>

  {{-- Delete confirmation; a principal that still has vessels cannot go --}}
  <div id="delete-modal" class="hidden fixed inset-0 bg-slate-900/40 items-center justify-center p-6 z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
      <h2 class="text-base font-semibold text-slate-900">Delete principal?</h2>
      <p class="text-sm text-muted mt-2"><span id="delete-name" class="font-medium text-slate-700"></span> will be removed.</p>
      <p id="delete-blocked" class="hidden text-sm text-rose-600 mt-2"></p>
      <form method="POST" id="delete-form" class="mt-5 flex justify-end gap-2">
        @csrf @method('DELETE')
        <button type="button" id="delete-cancel" class="text-sm text-muted hover:text-slate-900 px-3 py-2">Cancel</button>
        <button id="delete-confirm" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-md px-4 py-2">Delete</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      document.querySelectorAll('#filters select').forEach(function (select) {
        select.addEventListener('change', function () { document.getElementById('filters').submit(); });
      });

      var box = document.querySelector('#filters input[name="search"]');
      var timer;
      box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { document.getElementById('filters').submit(); }, 500);
      });

      var modal = document.getElementById('delete-modal');
      var blocked = document.getElementById('delete-blocked');
      var confirmButton = document.getElementById('delete-confirm');

      document.querySelectorAll('.delete-principal').forEach(function (button) {
        button.addEventListener('click', function () {
          var vessels = parseInt(button.dataset.vessels || '0', 10);

          document.getElementById('delete-name').textContent = button.dataset.name;
          document.getElementById('delete-form').action = button.dataset.url;

          blocked.textContent = vessels > 0
            ? 'Still has ' + vessels + ' vessel(s) in ERP HPY. Move them first.'
            : '';
          blocked.classList.toggle('hidden', vessels === 0);
          confirmButton.disabled = vessels > 0;
          confirmButton.classList.toggle('opacity-50', vessels > 0);

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
