{{-- Legend: a colour swatch beside each name; the text itself stays in text ink. --}}
@props(['items' => []])

<ul {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-600') }}>
  @foreach($items as $name => $color)
    <li class="flex items-center gap-1.5">
      <span class="size-2.5 rounded-[3px] shrink-0" style="background: {{ $color }}"></span>{{ $name }}
    </li>
  @endforeach
</ul>
