@props(['nav' => [], 'onHero' => false])
{{-- No-JS mobile menu: a <details> disclosure, wordmark + CTA stay visible, links drop below. --}}
<details class="relative md:hidden">
    <summary class="flex size-9 cursor-pointer list-none items-center justify-center rounded-full border text-base {{ $onHero ? 'border-white/60 text-white' : 'border-ink-200 text-ink-700 hover:border-ink-400' }}" aria-label="{{ __('Menu') }}">≡</summary>
    <nav class="absolute right-0 top-11 z-40 flex w-56 flex-col gap-1 rounded-2xl border border-ink-200 bg-white p-2 shadow-float" aria-label="Main">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}" class="rounded-full px-3.5 py-2 text-[13px] {{ $item['active'] ? 'bg-brand-50 font-medium text-brand-600' : 'text-ink-700 hover:bg-ink-100' }}">{{ __($item['label']) }}</a>
        @endforeach
        @guest
            <a href="{{ route('login') }}" class="rounded-full px-3.5 py-2 text-[13px] text-ink-700 hover:bg-ink-100">{{ __('Log in') }}</a>
        @endguest
    </nav>
</details>
