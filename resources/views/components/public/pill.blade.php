@props(['tone' => 'ink'])
@php($classes = match ($tone) {
    'mint' => 'bg-mint-100 text-mint-700',
    'blush' => 'bg-blush-100 text-blush-700',
    'coral' => 'bg-coral-100 text-coral-700',
    'brand' => 'bg-brand-100 text-brand-600',
    'dark' => 'bg-ink-700 text-white',
    default => 'bg-ink-100 text-ink-700',
})
@php($dot = match ($tone) { 'mint' => 'bg-mint-500', 'blush' => 'bg-blush-500', 'coral' => 'bg-coral-500', 'brand' => 'bg-brand-500', default => null })
<span {{ $attributes->merge(['class' => 'inline-flex h-7 items-center gap-1.5 rounded-full px-3 text-xs font-medium '.$classes]) }}>@if ($dot)<span class="size-1.5 rounded-full {{ $dot }}"></span>@endif{{ $slot }}</span>
