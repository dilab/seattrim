@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'jsonLd' => null])
{{-- Marketing layout (design-system.html): cool page ground, Manrope, and on every page the light field
     (blue → lavender → blush gradient with masked halftone dots) holding the glassy pill nav and the page's
     `hero` slot. Home fills the field with its mockup scene; other pages use <x-public.page-hero>. Light only. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ filled($title) ? $title.' · '.config('app.name') : config('app.name').' · Reclaim unused Zoom licenses' }}</title>
        <meta name="description" content="{{ $description ?? 'SeatTrim finds the Zoom licenses nobody uses and downgrades them safely, so you buy fewer seats and pay for fewer at renewal.' }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
        @if ($noindex)<meta name="robots" content="noindex,follow">@endif
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $description ?? 'Reclaim unused Zoom licenses before your renewal.' }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="theme-color" content="#3d7bff">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @if ($jsonLd)
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-page font-display text-ink-900 antialiased">
        @php($nav = [
            ['label' => 'Product', 'route' => 'home', 'active' => request()->routeIs('home')],
            ['label' => 'Pricing', 'route' => 'pricing', 'active' => request()->routeIs('pricing')],
            ['label' => 'Free audit', 'route' => 'free-audit', 'active' => request()->routeIs('free-audit')],
            ['label' => 'Docs', 'route' => 'docs.index', 'active' => request()->routeIs('docs.*')],
            ['label' => 'Blog', 'route' => 'blog.index', 'active' => request()->routeIs('blog.*')],
        ])

        <div class="mx-auto flex w-full max-w-[1180px] flex-col gap-24 px-4 py-4 sm:px-6 md:gap-32">
            <div class="relative overflow-hidden rounded-4xl shadow-float" style="background:radial-gradient(ellipse 55% 60% at 8% 95%, #f2a9d4 0%, rgba(242,169,212,0) 65%), radial-gradient(ellipse 45% 70% at 95% 80%, #e6b8e2 0%, rgba(230,184,226,0) 60%), radial-gradient(ellipse 90% 90% at 50% -10%, #5f8ffb 0%, #8fb3ff 45%, #c9d6fb 100%)">
                <div class="pointer-events-none absolute inset-0 opacity-50" style="background-image:radial-gradient(rgba(255,255,255,.8) 1px, transparent 1.3px);background-size:9px 9px;mask-image:radial-gradient(ellipse 55% 45% at 50% 70%, #000 0%, transparent 100%);-webkit-mask-image:radial-gradient(ellipse 55% 45% at 50% 70%, #000 0%, transparent 100%)"></div>
                <div class="relative flex flex-col items-center gap-8 px-5 pt-5 sm:px-8 sm:pt-6">
                    <div class="flex w-full items-center justify-between gap-3">
                        <a href="{{ route('home') }}" class="flex items-center gap-2 text-white" aria-label="SeatTrim home">
                            <x-app-logo-icon class="size-8 text-white" />
                            <span class="text-[17px] tracking-tight"><span class="font-semibold">Seat</span><span class="font-normal text-white/80">Trim</span></span>
                        </a>
                        <nav class="hidden gap-1 rounded-full border border-white/30 bg-white/25 p-1 backdrop-blur md:inline-flex" aria-label="Main">
                            @foreach ($nav as $item)
                                <a href="{{ route($item['route']) }}" class="inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[13px] {{ $item['active'] ? 'bg-white/90 font-medium text-ink-900' : 'text-white hover:bg-white/20' }}">
                                    @if ($item['active'])<span class="size-1 rounded-full bg-brand-500"></span>@endif{{ __($item['label']) }}
                                </a>
                            @endforeach
                        </nav>
                        <div class="flex items-center gap-2">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex h-9 items-center rounded-full bg-white px-4 text-[13px] font-medium text-ink-900 shadow-card hover:bg-brand-50">↳ {{ __('Open dashboard') }}</a>
                            @else
                                <a href="{{ route('login') }}" class="hidden h-9 items-center rounded-full px-3 text-[13px] text-white hover:bg-white/15 sm:inline-flex">{{ __('Log in') }}</a>
                                <a href="{{ route('register') }}" class="inline-flex h-9 items-center rounded-full bg-white px-4 text-[13px] font-medium text-ink-900 shadow-card hover:bg-brand-50">↳ {{ __('Try for free') }}</a>
                            @endauth
                            <x-public.mobile-menu :nav="$nav" />
                        </div>
                    </div>
                    {{ $hero ?? '' }}
                </div>
            </div>

            <main class="flex flex-col gap-24 md:gap-32">
                {{ $slot }}
            </main>

            <footer class="flex flex-col gap-10 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                <div class="grid grid-cols-2 gap-8 text-[13px] md:grid-cols-[180px_repeat(4,1fr)]">
                    <div class="col-span-2 md:col-span-1">
                        <a href="{{ route('home') }}" class="inline-flex h-9 items-center gap-2 rounded-full bg-brand-50 px-3 text-brand-600" aria-label="SeatTrim home">
                            <x-app-logo-icon class="size-5 text-brand-500" />
                            <x-app-wordmark class="text-[14px] text-ink-900" />
                        </a>
                        <p class="mt-4 text-xs leading-relaxed text-ink-500">{{ __('Quiet software for seats nobody is sitting in. A product of StaticMaker Pte Ltd, Singapore.') }}</p>
                    </div>
                    <div class="flex flex-col gap-2.5">
                        <div class="font-medium text-ink-900">{{ __('Product') }}</div>
                        <a href="{{ route('home') }}#how" class="text-ink-500 hover:text-ink-900">{{ __('How it works') }}</a>
                        <a href="{{ route('pricing') }}" class="text-ink-500 hover:text-ink-900">{{ __('Pricing') }}</a>
                        <a href="{{ route('free-audit') }}" class="text-ink-500 hover:text-ink-900">{{ __('Free Zoom license audit') }}</a>
                        <a href="{{ route('demo') }}" class="text-ink-500 hover:text-ink-900">{{ __('Live demo') }}</a>
                    </div>
                    <div class="flex flex-col gap-2.5">
                        <div class="font-medium text-ink-900">{{ __('Resources') }}</div>
                        <a href="{{ route('docs.index') }}" class="text-ink-500 hover:text-ink-900">{{ __('Documentation') }}</a>
                        <a href="{{ route('docs.show', 'add-the-app') }}" class="text-ink-500 hover:text-ink-900">{{ __('Adding the app') }}</a>
                        <a href="{{ route('docs.show', 'remove-the-app') }}" class="text-ink-500 hover:text-ink-900">{{ __('Removing the app') }}</a>
                        <a href="{{ route('blog.index') }}" class="text-ink-500 hover:text-ink-900">{{ __('Blog') }}</a>
                    </div>
                    <div class="flex flex-col gap-2.5">
                        <div class="font-medium text-ink-900">{{ __('Company') }}</div>
                        <a href="{{ route('support') }}" class="text-ink-500 hover:text-ink-900">{{ __('Support') }}</a>
                        <a href="mailto:hello@seattrim.com" class="text-ink-500 hover:text-ink-900">{{ __('Contact') }}</a>
                    </div>
                    <div class="flex flex-col gap-2.5">
                        <div class="font-medium text-ink-900">{{ __('Legal') }}</div>
                        <a href="{{ route('privacy') }}" class="text-ink-500 hover:text-ink-900">{{ __('Privacy') }}</a>
                        <a href="{{ route('terms') }}" class="text-ink-500 hover:text-ink-900">{{ __('Terms') }}</a>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 pt-6 text-xs text-ink-400">
                    <span>{{ __('SeatTrim is not affiliated with Zoom Video Communications, Inc.') }}</span>
                    <span class="inline-flex h-7 items-center gap-2 rounded-full bg-ink-100 px-3 text-[11px] font-medium text-ink-700">{{ __('Available on Zoom App Marketplace') }}</span>
                </div>
            </footer>
        </div>
    </body>
</html>
