<!doctype html>
<html lang="id">
<head>
<title>Masuk — HPYMarine</title>
@include('layouts.partials.head')
</head>
<body class="min-h-screen flex font-sans text-slate-900 bg-white">

  {{-- Brand panel: left column, wide screens only (same pattern as HPY Karyawan) --}}
  <div class="hidden lg:block w-[52%] xl:w-[56%]">
    @include('auth.partials.login-brand')
  </div>

  {{-- Form column --}}
  <div class="relative flex flex-1 flex-col bg-white">
    <div class="flex flex-1 items-center justify-center px-6 py-12">
      <div class="w-full max-w-sm" data-enter>
        <img src="{{ route('brand.image', 'hpy-logo.png') }}" alt="HPY Marine" class="h-16 w-auto max-w-full object-contain mb-6">

        <h1 class="text-xl font-medium text-slate-900">Masuk</h1>
        <p class="mt-1 mb-6 text-sm text-muted">
          Gunakan akun ERP HPY Anda
          @if($erpUrl)
            <span class="text-slate-700">({{ parse_url($erpUrl, PHP_URL_HOST) }})</span>
          @endif
        </p>

        <form method="POST" action="{{ route('login') }}" class="space-y-4" data-login-form>
          @csrf

          @if($errors->any())
            <div class="rounded-lg bg-red-50 ring-1 ring-red-200 px-3 py-2 text-[13px] text-red-700" role="alert">{{ $errors->first() }}</div>
          @endif

          <div class="space-y-1">
            <label for="usr" class="block text-xs text-muted">Email atau Username</label>
            <input id="usr" name="usr" type="text" required autofocus autocomplete="username"
                   value="{{ old('usr') }}" placeholder="nama@perusahaan.co.id"
                   class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm placeholder:text-slate-400 transition-colors focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
          </div>

          <div class="space-y-1">
            <label for="pwd" class="block text-xs text-muted">Password</label>
            <div class="relative">
              <input id="pwd" name="pwd" type="password" required autocomplete="current-password" placeholder="••••••••"
                     class="w-full rounded-lg border border-slate-300 bg-slate-50 pl-3 pr-10 py-2 text-sm placeholder:text-slate-400 transition-colors focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
              <button type="button" tabindex="-1" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"
                      class="absolute right-0.5 top-1/2 -translate-y-1/2 size-8 rounded-lg flex items-center justify-center text-muted hover:text-slate-900 hover:bg-slate-100">
                <svg data-eye class="size-[15px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                <svg data-eye-off class="size-[15px] hidden" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3 3l18 18M10.6 10.6A3 3 0 0013.4 13.4M9.9 5.2A9.6 9.6 0 0112 5c6.4 0 10 7 10 7a17 17 0 01-3.2 4.1M6.3 6.4A17 17 0 002 12s3.6 7 10 7c1.2 0 2.3-.2 3.3-.6"/>
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" data-login-submit
                  class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-brand hover:bg-brand-d text-white text-sm font-medium py-2.5 shadow-sm transition-colors disabled:opacity-70">
            <svg data-spin class="size-4 animate-spin hidden" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <svg data-enter-icon class="size-[15px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/>
            </svg>
            <span data-label>Masuk</span>
          </button>

          <p class="text-center text-xs text-muted">
            Lupa password?
            @if($erpUrl)
              Reset di <a href="{{ rtrim($erpUrl, '/') }}/login#forgot" target="_blank" rel="noopener" class="text-brand hover:underline">ERP HPY</a>.
            @else
              Hubungi administrator ERP HPY perusahaan Anda.
            @endif
          </p>
        </form>
      </div>
    </div>

    <div class="flex flex-col items-center gap-1.5 px-6 pb-5 text-[11px] text-muted">
      <span class="uppercase tracking-wider">Powered by</span>
      <a href="https://hpysolution.com/?utm_source=hpymarine&utm_medium=app&utm_campaign=powered_by" target="_blank" rel="noopener">
        <img src="{{ route('brand.image', 'hpysolution.png') }}" alt="HPY Solution" class="h-7 w-auto object-contain">
      </a>
    </div>
  </div>

  <script>
    (function () {
      var form = document.querySelector('[data-login-form]');
      var toggle = form.querySelector('[data-password-toggle]');
      var pwd = document.getElementById('pwd');

      toggle.addEventListener('click', function () {
        var show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show);
        toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        toggle.querySelector('[data-eye]').classList.toggle('hidden', show);
        toggle.querySelector('[data-eye-off]').classList.toggle('hidden', !show);
      });

      form.addEventListener('submit', function () {
        var button = form.querySelector('[data-login-submit]');
        button.disabled = true;
        button.querySelector('[data-spin]').classList.remove('hidden');
        button.querySelector('[data-enter-icon]').classList.add('hidden');
        button.querySelector('[data-label]').textContent = 'Memproses…';
        form.querySelectorAll('input:not([type=hidden])').forEach(function (i) { i.readOnly = true; });
      });
    })();
  </script>
</body>
</html>
