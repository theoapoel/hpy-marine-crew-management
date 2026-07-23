<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — HPYMarine</title>
<link rel="icon" type="image/png" href="{{ route('brand.image', 'hpy-mark.png') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: { brand:'#2563eb', 'brand-d':'#1e40af', line:'#e2e8f0', muted:'#64748b' },
    fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] }
  } }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>html,body{font-family:Inter,sans-serif}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-d to-brand flex items-center justify-center p-6">

  <div class="w-full max-w-sm">

    <div class="bg-white rounded-2xl shadow-xl p-8">
      <div class="flex flex-col items-center text-center pb-6 mb-6 border-b border-line">
        <img src="{{ route('brand.image', 'hpy-logo.png') }}" alt="HPY" class="h-12 w-auto object-contain">
        <div class="text-sm font-semibold text-slate-900 mt-3">HPYMarine</div>
        <div class="text-[11px] text-muted">Crew Management</div>
      </div>

      <h1 class="text-lg font-semibold text-slate-900">Masuk</h1>
      <p class="text-xs text-muted mt-1">
        Gunakan akun ERP HPY Anda
        @if($erpUrl)
          <span class="text-slate-700 font-medium">({{ parse_url($erpUrl, PHP_URL_HOST) }})</span>
        @endif
      </p>

      @if($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[13px] text-red-700">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
        @csrf

        <div>
          <label for="usr" class="block text-[13px] font-medium text-slate-700 mb-1">Email / Username</label>
          <input id="usr" name="usr" type="text" required autofocus autocomplete="username"
                 value="{{ old('usr') }}"
                 class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand"
                 placeholder="nama@hpy.co.id">
        </div>

        <div>
          <label for="pwd" class="block text-[13px] font-medium text-slate-700 mb-1">Password</label>
          <input id="pwd" name="pwd" type="password" required autocomplete="current-password"
                 class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-brand hover:bg-brand-d text-white text-sm font-medium py-2.5 transition">
          Login
        </button>
      </form>

      @if($erpUrl)
        <p class="mt-4 text-[11px] text-muted text-center">
          Lupa password? Reset di
          <a href="{{ rtrim($erpUrl, '/') }}/login#forgot" target="_blank" rel="noopener"
             class="text-brand hover:underline">ERP HPY</a>.
        </p>
      @endif
    </div>
  </div>

</body>
</html>
