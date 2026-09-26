@props(['glass' => false])
<span {{ $attributes->merge(['class' => 'inline-flex h-7 w-fit items-center gap-1.5 rounded-full px-3 text-xs font-medium text-brand-600 '.($glass ? 'bg-white/70 backdrop-blur' : 'bg-brand-50')]) }}><span class="size-1 rounded-full bg-brand-500"></span>{{ $slot }}</span>
