<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'HPYMarine')</title>
<link rel="icon" type="image/png" href="{{ route('brand.image', 'hpy-mark.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand:     '#2563eb',
        'brand-d': '#1e40af',
        accent:    '#16a34a',
        panel:     '#ffffff',
        canvas:    '#f1f5f9',
        ink:       '#f8fafc',
        line:      '#e2e8f0',
        muted:     '#64748b',
      },
      fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] }
    }
  }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  html,body{background:#f1f5f9;color:#0f172a;font-family:Inter,sans-serif}
  ::-webkit-scrollbar{width:8px;height:8px}
  ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
  .chip{font-size:10px;padding:2px 8px;border-radius:999px;font-weight:600}
</style>
</head>
<body class="min-h-screen">

@php
  $erpUser = session('erpnext', []);
  $erpName = $erpUser['full_name'] ?? 'Guest';
  $erpMail = $erpUser['user'] ?? '';
  $erpInitial = mb_strtoupper(mb_substr($erpName, 0, 1));
  $erpCompany = session(\App\Services\Erpnext\ErpnextClient::COMPANY_KEY);
  $erpCompanies = session(\App\Http\Controllers\CompanyController::LIST_KEY, array_filter([$erpCompany]));
@endphp

<div class="flex min-h-screen">

  {{-- Sidebar --}}
  <aside class="w-64 shrink-0 bg-gradient-to-b from-brand-d to-brand flex flex-col text-white">
    <div class="px-5 py-4 border-b border-white/10">
      <a href="{{ url('/') }}" class="block">
        <div class="bg-white rounded-lg px-4 py-3 flex items-center justify-center">
          <img src="{{ route('brand.image', 'hpy-logo.png') }}" alt="HPY" class="h-8 w-auto object-contain">
        </div>
        <div class="mt-2 text-center">
          <div class="font-semibold text-white leading-tight">HPYMarine</div>
          <div class="text-[11px] text-blue-100/80">Crew Management</div>
        </div>
      </a>
    </div>

    @php
      $nav = [
        'Dashboard' => [['Dashboard', url('/'), request()->is('/')]],
        'Crewing' => [
          ['Crew Master', route('crew.index'), request()->is('crew*')],
          ['Crew Assignment', route('assignments.index'), request()->is('assignments*')],
          ['Sign On', route('assignments.sign-on'), request()->is('sign-on')],
          ['Sign Off', route('assignments.sign-off'), request()->is('sign-off')],
        ],
        'Recruitment' => [
          ['Candidate Pool', route('candidates.index'), request()->is('candidates*')],
          ['Recruitment Pipeline', route('applications.index'), request()->is('applications*')],
        ],
        'Documents' => [
          ['Certificates', route('documents', 'certificates'), request()->is('documents/certificates')],
          ['Passport', route('documents', 'passport'), request()->is('documents/passport')],
          ['Seaman Book', route('documents', 'seaman-book'), request()->is('documents/seaman-book')],
          ['Medical', route('documents', 'medical'), request()->is('documents/medical')],
          ['Expiring Documents', route('documents', 'expiring'), request()->is('documents/expiring')],
        ],
        'Principal & Vessel' => [
          ['Principals', route('principals.index'), request()->is('principals*')],
          ['Vessels', route('vessels.index'), request()->is('vessels*')],
          ['Vessel Profitability', '#', false],
        ],
        'Finance' => [
          ['Cash In', '#', false], ['Cash Out', '#', false],
          ['Invoices', '#', false], ['Payroll', '#', false], ['Crew Loans', '#', false],
        ],
        'Accounting' => [
          ['General Ledger', '#', false], ['Trial Balance', '#', false],
          ['Balance Sheet', '#', false], ['Profit & Loss', '#', false],
        ],
        'Settings' => [
          ['Users', '#', false], ['Roles', '#', false],
          ['Workflow', '#', false], ['Master Data', '#', false],
        ],
      ];
    @endphp

    <nav class="flex-1 overflow-y-auto py-3 px-2 text-sm">
      @foreach($nav as $group => $items)
        <div class="mt-3 first:mt-0">
          <div class="px-3 text-[10px] uppercase tracking-wider text-blue-200/70 font-semibold mb-1">{{ $group }}</div>
          @foreach($items as $item)
            @php
              $active = $item[2] ?? false;
              // '#' marks a module that has no page yet: shown, but plainly not a link.
              $ready = ($item[1] ?? '#') !== '#';
            @endphp

            @if($ready)
              <a href="{{ $item[1] }}" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-[13px] {{ $active ? 'bg-white/15 text-white font-medium' : 'text-blue-100/90 hover:bg-white/10' }}">
                <span class="w-1 h-1 rounded-full {{ $active ? 'bg-white' : 'bg-blue-200/60' }}"></span>
                {{ $item[0] }}
              </a>
            @else
              <span title="Belum dibuat" class="flex items-center gap-2 px-3 py-1.5 rounded-md text-[13px] text-blue-100/40 cursor-not-allowed">
                <span class="w-1 h-1 rounded-full bg-blue-200/25"></span>
                {{ $item[0] }}
                <span class="ml-auto text-[9px] uppercase tracking-wider text-blue-200/40 border border-blue-200/20 rounded px-1">soon</span>
              </span>
            @endif
          @endforeach
        </div>
      @endforeach
    </nav>

    <div class="p-4 border-t border-white/10 flex items-center gap-3">
      <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs font-semibold">{{ $erpInitial }}</div>
      <div class="min-w-0">
        <div class="text-sm text-white truncate">{{ $erpName }}</div>
        <div class="text-[11px] text-blue-100/70 truncate">{{ $erpMail }}</div>
      </div>
    </div>
  </aside>

  {{-- Main --}}
  <div class="flex-1 flex flex-col min-w-0">

    <header class="h-14 border-b border-line bg-white flex items-center px-6 gap-4">
      <div class="text-sm text-slate-900 font-semibold">@yield('heading', 'Dashboard')</div>

      {{-- Company switcher: everything on the page is scoped to this company. --}}
      <form method="POST" action="{{ route('company.store') }}" class="ml-auto flex items-center gap-2">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
        <span class="text-[10px] uppercase tracking-wider text-muted font-semibold">Company</span>
        <select name="company" onchange="this.form.submit()"
                class="text-xs text-slate-900 bg-white border border-line rounded-md px-2 py-1.5 max-w-[220px] focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
          @foreach($erpCompanies as $company)
            <option value="{{ $company }}" @selected($company === $erpCompany)>{{ $company }}</option>
          @endforeach
        </select>
      </form>

      <div class="flex items-center gap-3 pl-3 border-l border-line">
        <div class="w-8 h-8 rounded-full bg-brand/10 text-brand flex items-center justify-center text-xs font-semibold">{{ $erpInitial }}</div>
        <div class="leading-tight">
          <div class="text-xs text-slate-900 font-medium">{{ $erpName }}</div>
          <div class="text-[10px] text-muted">{{ $erpMail }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="text-[11px] text-muted hover:text-red-600 border border-line rounded-md px-2 py-1">
            Logout
          </button>
        </form>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-6 space-y-6">
      @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-2">
          {{ session('success') }}
        </div>
      @endif

      @yield('content')
    </main>
  </div>
</div>

{{-- Tabs: any [data-tab-group] gets its panels switched instead of stacked, so long
     forms and detail pages stay on one screen. Hidden panels still submit their
     inputs; an invalid field simply pulls its own tab open first. --}}
<script>
  window.__formErrors = @json($errors->keys());

  (function () {
    document.querySelectorAll('[data-tab-group]').forEach(function (group) {
      var buttons = group.querySelectorAll('[data-tab-target]');
      var panels = group.querySelectorAll('[data-tab-panel]');

      function show(key) {
        panels.forEach(function (panel) {
          panel.classList.toggle('hidden', panel.dataset.tabPanel !== key);
        });
        buttons.forEach(function (button) {
          var on = button.dataset.tabTarget === key;
          button.classList.toggle('border-brand', on);
          button.classList.toggle('text-brand', on);
          button.classList.toggle('font-medium', on);
          button.classList.toggle('border-transparent', ! on);
          button.classList.toggle('text-muted', ! on);
        });
        group.dataset.activeTab = key;
      }

      buttons.forEach(function (button) {
        button.addEventListener('click', function () { show(button.dataset.tabTarget); });
      });

      // A panel holding a field the server rejected wins; otherwise the first one.
      var invalidNames = (window.__formErrors || []);
      var withError = null;

      invalidNames.some(function (name) {
        var field = group.querySelector('[name="' + name + '"]');
        withError = field ? field.closest('[data-tab-panel]') : null;

        return !! withError;
      });

      show(withError ? withError.dataset.tabPanel : (panels[0] ? panels[0].dataset.tabPanel : ''));

      // Native validation cannot focus a hidden field: open its tab first.
      group.addEventListener('invalid', function (event) {
        var panel = event.target.closest('[data-tab-panel]');
        if (panel && panel.classList.contains('hidden')) {
          show(panel.dataset.tabPanel);
          setTimeout(function () { event.target.focus(); }, 0);
        }
      }, true);
    });
  })();
</script>

</body>
</html>
