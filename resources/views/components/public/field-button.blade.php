@props(['variant' => 'white', 'href'])
{{-- Buttons that sit on the light field (design-system "On the hero field"): white primary or white outline. --}}
@php($classes = $variant === 'outline'
    ? 'border border-white/60 text-white hover:bg-white/15'
    : 'bg-white text-ink-900 shadow-card hover:bg-brand-50')
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex h-11 items-center rounded-full px-5 text-sm font-medium transition active:scale-[.98] '.$classes]) }}>{{ $slot }}</a>
