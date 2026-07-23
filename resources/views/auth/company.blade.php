<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pilih Company — HPYMarine</title>
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
        <div class="text-[11px] text-muted mt-1">{{ session('erpnext.full_name') }}</div>
      </div>

      <h1 class="text-lg font-semibold text-slate-900">Company</h1>
      {{-- <p class="text-xs text-muted mt-1">Data yang tampil mengikuti company yang Anda pilih.</p> --}}

      @if($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[13px] text-red-700">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('company.store') }}" class="mt-5 space-y-2">
        @csrf

        @forelse($companies as $company)
          @php $profile = $profiles[$company] ?? ['logo' => null, 'abbr' => null]; @endphp
          <button type="submit" name="company" value="{{ $company }}"
                  class="w-full flex items-center gap-3 text-left rounded-lg border px-4 py-3 text-sm transition
                         {{ $current === $company
                            ? 'border-brand bg-brand/5 text-brand font-medium'
                            : 'border-line hover:border-brand hover:bg-brand/5 text-slate-800' }}">
            @if($profile['logo'])
              <img src="{{ route('erp.file', ['path' => $profile['logo']]) }}" alt=""
                   class="w-10 h-10 rounded object-contain border border-line bg-white shrink-0">
            @else
              <span class="w-10 h-10 rounded bg-slate-100 text-muted flex items-center justify-center text-[11px] font-semibold shrink-0">
                {{ $profile['abbr'] ?: mb_strtoupper(mb_substr($company, 0, 2)) }}
              </span>
            @endif
            <span class="min-w-0">
              <span class="block truncate">{{ $company }}</span>
              @if($profile['abbr'])
                <span class="block text-[11px] text-muted">{{ $profile['abbr'] }}</span>
              @endif
            </span>
          </button>
        @empty
          <p class="text-[13px] text-red-700">
            Akun Anda tidak punya akses ke company mana pun di ERP HPY.
          </p>
        @endforelse
      </form>

      <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
        @csrf
        <button class="text-[11px] text-muted hover:text-red-600">Keluar</button>
      </form>
    </div>
  </div>

</body>
</html>
