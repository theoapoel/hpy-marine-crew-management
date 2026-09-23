<!doctype html>
<html lang="en">
<head>
<title>@yield('title', 'HPYMarine')</title>
@include('layouts.partials.head')
</head>
<body class="min-h-screen font-sans text-slate-900 bg-canvas">

@php
  $erpUser = session('erpnext', []);
  $erpName = $erpUser['full_name'] ?? 'Guest';
  $erpMail = $erpUser['user'] ?? '';
  $erpInitial = mb_strtoupper(mb_substr($erpName, 0, 1));
  $erpCompany = session(\App\Services\Erpnext\ErpnextClient::COMPANY_KEY);
  $erpCompanies = session(\App\Http\Controllers\CompanyController::LIST_KEY, array_filter([$erpCompany]));

  // [label, url, active, lucide icon]; '#' marks a module that has no page yet.
  $nav = [
    'Overview' => [['Dashboard', url('/'), request()->is('/'), 'layout-dashboard']],
    'Crewing' => [
      ['Crew Master', route('crew.index'), request()->is('crew*'), 'users'],
      ['Crew Assignment', route('assignments.index'), request()->is('assignments*'), 'clipboard-list'],
      ['Sign On', route('assignments.sign-on'), request()->is('sign-on'), 'log-in'],
      ['Sign Off', route('assignments.sign-off'), request()->is('sign-off'), 'log-out'],
    ],
    'Recruitment' => [
      ['Candidate Pool', route('candidates.index'), request()->is('candidates*'), 'user-search'],
      ['Recruitment Pipeline', route('applications.index'), request()->is('applications*'), 'square-kanban'],
    ],
    'Documents' => [
      ['Certificates', route('documents', 'certificates'), request()->is('documents/certificates'), 'file-badge'],
      ['Passport', route('documents', 'passport'), request()->is('documents/passport'), 'book-user'],
      ['Seaman Book', route('documents', 'seaman-book'), request()->is('documents/seaman-book'), 'book-text'],
      ['Medical', route('documents', 'medical'), request()->is('documents/medical'), 'stethoscope'],
      ['Expiring Documents', route('documents', 'expiring'), request()->is('documents/expiring'), 'alarm-clock'],
      ['Document Types', route('crew.document-types.index'), request()->is('crew/document-types*'), 'list-plus'],
    ],
    'Principal & Vessel' => [
      ['Principals', route('principals.index'), request()->is('principals*'), 'building-2'],
      ['Vessels', route('vessels.index'), request()->is('vessels*'), 'ship'],
      ['Vessel Profitability', route('profitability.index'), request()->is('profitability*'), 'trending-up'],
    ],
    'Finance' => [
      ['Cash In', '#', false, 'arrow-down-to-line'], ['Cash Out', '#', false, 'arrow-up-from-line'],
      ['Invoices', '#', false, 'receipt'], ['Payroll', '#', false, 'wallet'], ['Crew Loans', '#', false, 'hand-coins'],
    ],
    'Accounting' => [
      ['General Ledger', route('accounting', 'general-ledger'), request()->is('accounting/general-ledger'), 'book-text'],
      ['Trial Balance', route('accounting', 'trial-balance'), request()->is('accounting/trial-balance'), 'scale'],
      ['Balance Sheet', route('accounting', 'balance-sheet'), request()->is('accounting/balance-sheet'), 'landmark'],
      ['Profit & Loss', route('accounting', 'profit-and-loss'), request()->is('accounting/profit-and-loss'), 'calculator'],
    ],
    'Settings' => [
      ['Users', '#', false, 'user-cog'], ['Roles', '#', false, 'shield-check'],
      ['Workflow', '#', false, 'workflow'], ['Master Data', '#', false, 'database'],
    ],
  ];
@endphp

<div class="flex min-h-screen">

  {{-- Sidebar: fixed column on desktop, slide-in drawer below lg --}}
  <div id="sidebar-backdrop" class="hidden fixed inset-0 z-30 bg-navy/50 backdrop-blur-[2px] lg:hidden"></div>

  <aside id="sidebar" aria-label="Main navigation"
         class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 h-screen shrink-0 bg-navy text-white flex flex-col">
    <div class="px-4 pt-5 pb-4 flex items-center gap-2">
      <a href="{{ url('/') }}" class="flex-1 min-w-0 flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-white/5 transition-colors">
        @php $companyLogo = \App\Support\Brand::companyLogo(); @endphp
        @if($companyLogo)
          {{-- The company's own logo from ERP HPY, as it is (transparent) --}}
          <img src="{{ $companyLogo }}" alt="{{ session('erpnext_company') }}" class="h-9 w-auto max-w-[5.5rem] object-contain shrink-0">
        @else
          <div class="bg-white rounded-lg p-1.5 flex items-center justify-center shrink-0">
            <img src="{{ \App\Support\Brand::logo() }}" alt="HPY" class="h-7 w-auto object-contain">
          </div>
        @endif
        <div class="min-w-0 leading-tight">
          <div class="font-semibold text-white truncate">HPYMarine</div>
          <div class="text-[11px] text-slate-400">Crew Management</div>
        </div>
      </a>
      <button type="button" data-sidebar-close aria-label="Close menu"
              class="lg:hidden size-10 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 flex items-center justify-center">
        <i data-lucide="x" class="size-5"></i>
      </button>
    </div>

    <nav class="nav-scroll flex-1 overflow-y-auto pb-4 px-3 text-sm space-y-5">
      @foreach($nav as $group => $items)
        <div>
          <div class="px-3 text-[10px] uppercase tracking-[0.14em] text-slate-500 font-semibold mb-1.5">{{ $group }}</div>
          <div class="space-y-0.5">
            @foreach($items as [$label, $href, $active, $icon])
              @if($href !== '#')
                <a href="{{ $href }}" @if($active) aria-current="page" @endif
                   class="group relative flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-colors duration-150
                          {{ $active ? 'bg-brand text-white font-medium shadow-sm shadow-brand/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                  <i data-lucide="{{ $icon }}" class="size-4 shrink-0 {{ $active ? 'text-white' : 'text-slate-500 group-hover:text-slate-200' }}"></i>
                  <span class="truncate">{{ $label }}</span>
                </a>
              @else
                <span title="Not built yet" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] text-slate-500 cursor-not-allowed">
                  <i data-lucide="{{ $icon }}" class="size-4 shrink-0 text-slate-600"></i>
                  <span class="truncate">{{ $label }}</span>
                  <span class="ml-auto text-[9px] uppercase tracking-wider text-slate-500 border border-slate-700 rounded px-1 py-px">soon</span>
                </span>
              @endif
            @endforeach
          </div>
        </div>
      @endforeach
    </nav>

    <div class="m-3 p-3 rounded-xl bg-white/5 ring-1 ring-white/10 flex items-center gap-3">
      <div class="size-9 rounded-full bg-brand text-white flex items-center justify-center text-xs font-semibold shrink-0">{{ $erpInitial }}</div>
      <div class="min-w-0 flex-1">
        <div class="text-sm text-white truncate">{{ $erpName }}</div>
        <div class="text-[11px] text-slate-400 truncate">{{ $erpMail }}</div>
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" aria-label="Logout" title="Logout"
                class="size-9 rounded-lg text-slate-400 hover:text-rose-300 hover:bg-white/10 flex items-center justify-center transition-colors">
          <i data-lucide="log-out" class="size-4"></i>
        </button>
      </form>
    </div>
  </aside>

  {{-- Main --}}
  <div class="flex-1 flex flex-col min-w-0">

    <header class="sticky top-0 z-20 h-16 border-b border-line bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70 flex items-center px-4 lg:px-8 gap-3">
      <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"
              class="lg:hidden -ml-1 size-10 rounded-lg text-slate-600 hover:bg-slate-100 flex items-center justify-center">
        <i data-lucide="menu" class="size-5"></i>
      </button>

      <div class="text-[15px] text-slate-900 font-semibold truncate">@yield('heading', 'Dashboard')</div>

      {{-- Company switcher: everything on the page is scoped to this company. --}}
      <form method="POST" action="{{ route('company.store') }}" class="ml-auto flex items-center gap-2 min-w-0">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
        <label for="company-switch" class="hidden sm:flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted font-semibold">
          <i data-lucide="building-2" class="size-3.5"></i> Company
        </label>
        <select id="company-switch" name="company" onchange="this.form.submit()"
                class="text-xs text-slate-900 bg-white border border-line rounded-lg pl-2.5 pr-7 py-2 max-w-[160px] sm:max-w-[240px] truncate transition-colors hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
          @foreach($erpCompanies as $company)
            <option value="{{ $company }}" @selected($company === $erpCompany)>{{ $company }}</option>
          @endforeach
        </select>
      </form>

      <div class="hidden md:flex items-center gap-3 pl-3 border-l border-line">
        <div class="size-8 rounded-full bg-brand/10 text-brand flex items-center justify-center text-xs font-semibold">{{ $erpInitial }}</div>
        <div class="leading-tight">
          <div class="text-xs text-slate-900 font-medium">{{ $erpName }}</div>
          <div class="text-[11px] text-muted">{{ $erpMail }}</div>
        </div>
      </div>
    </header>

    <main class="flex-1 p-4 lg:p-8 space-y-6">
      @if(session('success'))
        <div data-flash role="status" class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl px-4 py-3">
          <span class="mt-1.5 size-2 rounded-full bg-emerald-500 shrink-0"></span>
          <span class="flex-1">{{ session('success') }}</span>
          <button type="button" data-flash-close aria-label="Dismiss" class="-my-1 -mr-1 size-7 rounded-md text-emerald-700 hover:bg-emerald-100 flex items-center justify-center">
            <i data-lucide="x" class="size-4"></i>
          </button>
        </div>
      @endif

      @yield('content')
    </main>
  </div>
</div>

<script>window.__formErrors = @json($errors->keys());</script>

</body>
</html>
