@props(['label', 'heading' => null, 'muted' => null, 'intro' => null])
{{-- Compact page hero inside the layout's light field: glass label, white two-tone headline, intro,
     optional `meta` (pill + date above the h1), `actions` (buttons) and `aside` (glass tile → two columns).
     Carries its own bottom padding so the landing hero, which bleeds to the field's edge, is unaffected. --}}
@php($twoColumn = isset($aside))
<div class="w-full pt-2 pb-10 sm:pt-4 sm:pb-14">
    <div class="{{ $twoColumn ? 'grid items-start gap-8 md:grid-cols-[1.4fr_1fr]' : 'mx-auto flex max-w-2xl flex-col items-center text-center' }}">
        <div class="flex flex-col gap-4 {{ $twoColumn ? 'items-start' : 'items-center' }}">
            <x-public.section-label glass>{{ $label }}</x-public.section-label>
            @isset($meta)
                <div class="flex items-center gap-2 text-[12px] text-white/70">{{ $meta }}</div>
            @endisset
            @if ($heading)
                <h1 class="text-[32px] font-light leading-[1.1] tracking-[-0.02em] text-white sm:text-[40px] md:text-[48px]" style="text-wrap:balance">{{ $heading }}@if ($muted) <span class="text-white/70">{{ $muted }}</span>@endif</h1>
            @endif
            @if ($intro)
                <p class="max-w-xl text-[15px] leading-relaxed text-white/85">{{ $intro }}</p>
            @endif
            @isset($actions)
                <div class="flex flex-wrap gap-3 pt-1 {{ $twoColumn ? 'justify-start' : 'justify-center' }}">{{ $actions }}</div>
            @endisset
        </div>
        @isset($aside)
            <div class="flex flex-col gap-3 rounded-3xl border border-white/80 bg-white/60 p-6 text-left shadow-card backdrop-blur-md">{{ $aside }}</div>
        @endisset
    </div>
</div>
