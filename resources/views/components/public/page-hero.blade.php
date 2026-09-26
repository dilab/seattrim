@props(['label' => null, 'heading' => null, 'muted' => null, 'intro' => null, 'size' => 'compact'])
{{-- Hero text on the light field. Brings its own <x-public.field-band> so contrast is guaranteed wherever
     it sits. `compact` (inner pages): glass label, two-tone headline, intro, optional `meta`, `actions`
     and `aside` (glass tile → two columns), with bottom padding equal to the band's fade tail.
     `display` (home): larger type, no bottom padding, plus a `note` slot; the band fades into the
     top of whatever follows (the landing mockup, whose cards carry their own backgrounds). --}}
@php($twoColumn = isset($aside))
@php($display = $size === 'display')
<div class="relative w-full {{ $display ? 'pt-6 pb-0' : 'pt-1 pb-16 sm:pt-2' }}">
    <x-public.field-band :class="$display ? '-bottom-16' : ''" />
    <div class="{{ $twoColumn ? 'grid items-start gap-8 md:grid-cols-[1.4fr_1fr]' : 'mx-auto flex max-w-2xl flex-col items-center text-center' }}">
        <div class="flex flex-col {{ $display ? 'gap-5' : 'gap-4' }} {{ $twoColumn ? 'items-start' : 'items-center' }}">
            @if ($label)
                <x-public.section-label glass>{{ $label }}</x-public.section-label>
            @endif
            @isset($meta)
                <div class="flex items-center gap-2 text-[13px] text-white">{{ $meta }}</div>
            @endisset
            @if ($heading)
                <h1 class="font-normal tracking-[-0.02em] text-white {{ $display ? 'text-[40px] leading-[1.05] sm:text-[48px] md:text-[56px]' : 'text-[32px] leading-[1.1] sm:text-[40px] md:text-[48px]' }}" style="text-wrap:balance">{{ $heading }}@if ($muted) <span class="font-light text-brand-100">{{ $muted }}</span>@endif</h1>
            @endif
            @if ($intro)
                <p class="text-[16px] leading-[1.6] text-white {{ $display ? 'max-w-lg' : 'max-w-xl' }}">{{ $intro }}</p>
            @endif
            @isset($actions)
                <div class="flex flex-wrap gap-3 pt-1 {{ $twoColumn ? 'justify-start' : 'justify-center' }}">{{ $actions }}</div>
            @endisset
            @isset($note)
                <p class="text-[13px] text-white">{{ $note }}</p>
            @endisset
        </div>
        @isset($aside)
            <div class="flex flex-col gap-3 rounded-3xl border border-white/80 bg-white/60 p-6 text-left shadow-card backdrop-blur-md">{{ $aside }}</div>
        @endisset
    </div>
</div>
