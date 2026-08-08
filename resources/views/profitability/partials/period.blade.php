{{-- Both ends optional: an empty form reports everything ever posted, which is the
     right default for a ship that has been running for years. --}}
<form method="GET" class="flex flex-wrap items-end gap-2">
  <label class="text-xs text-muted">
    <span class="block mb-1">From</span>
    <input type="date" name="from" value="{{ $window['from'] }}"
           class="bg-white border border-line rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
  </label>
  <label class="text-xs text-muted">
    <span class="block mb-1">To</span>
    <input type="date" name="to" value="{{ $window['to'] }}"
           class="bg-white border border-line rounded-md py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
  </label>
  <button class="bg-white hover:bg-slate-50 border border-line text-slate-700 text-sm rounded-md px-4 py-2">Apply period</button>
  @if($window['from'] || $window['to'])
    <a href="{{ url()->current() }}" class="text-sm text-muted hover:text-slate-700 px-2 py-2">Clear</a>
  @endif
</form>
