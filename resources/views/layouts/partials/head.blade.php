<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="{{ \App\Support\Brand::icon() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@500;600&family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
{{-- Hide entrance-animated blocks until the animation takes them over; never for
     longer than 2s, and never when the visitor asked for reduced motion. --}}
<script>
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('motion-ready');
    setTimeout(function () { document.documentElement.classList.remove('motion-ready'); }, 2000);
  }
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
