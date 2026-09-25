@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'jsonLd' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ filled($title) ? $title.' · '.config('app.name') : config('app.name').' · Reclaim unused Zoom licenses' }}</title>
        <meta name="description" content="{{ $description ?? 'SeatTrim connects to your Zoom account, finds licensed seats nobody uses, and lets you downgrade them safely before your renewal.' }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
        @if ($noindex)<meta name="robots" content="noindex,follow">@endif
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $description ?? 'Reclaim unused Zoom licenses before your renewal.' }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @if ($jsonLd)
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                    <x-app-logo-icon class="size-7 fill-current text-blue-600" />
                    <span>{{ config('app.name') }}</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <a href="{{ route('pricing') }}" class="hover:underline">{{ __('Pricing') }}</a>
                    <a href="{{ route('free-audit') }}" class="hover:underline">{{ __('Free audit') }}</a>
                    <a href="{{ route('docs.index') }}" class="hover:underline">{{ __('Docs') }}</a>
                    <a href="{{ route('blog.index') }}" class="hover:underline">{{ __('Blog') }}</a>
                </nav>
                <div class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" size="sm" variant="primary">{{ __('Dashboard') }}</flux:button>
                    @else
                        <flux:button :href="route('login')" size="sm" variant="ghost">{{ __('Log in') }}</flux:button>
                        <flux:button :href="route('register')" size="sm" variant="primary">{{ __('Get started') }}</flux:button>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-4 py-10">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-zinc-200 py-10 text-sm text-zinc-500 dark:border-zinc-800">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 md:grid-cols-4">
                <div>
                    <div class="font-semibold text-zinc-700 dark:text-zinc-200">{{ config('app.name') }}</div>
                    <p class="mt-2">{{ __('Reclaim unused Zoom licenses. A product of StaticMaker Pte Ltd, Singapore.') }}</p>
                    <p class="mt-2">{{ __('SeatTrim is not affiliated with Zoom Video Communications, Inc.') }}</p>
                </div>
                <div>
                    <div class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Product') }}</div>
                    <ul class="mt-2 space-y-1">
                        <li><a href="{{ route('pricing') }}" class="hover:underline">{{ __('Pricing') }}</a></li>
                        <li><a href="{{ route('free-audit') }}" class="hover:underline">{{ __('Free Zoom license audit') }}</a></li>
                        <li><a href="{{ route('demo') }}" class="hover:underline">{{ __('Live demo') }}</a></li>
                    </ul>
                </div>
                <div>
                    <div class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Help') }}</div>
                    <ul class="mt-2 space-y-1">
                        <li><a href="{{ route('docs.index') }}" class="hover:underline">{{ __('Documentation') }}</a></li>
                        <li><a href="{{ route('docs.show', 'add-the-app') }}" class="hover:underline">{{ __('Adding the app') }}</a></li>
                        <li><a href="{{ route('docs.show', 'remove-the-app') }}" class="hover:underline">{{ __('Removing the app') }}</a></li>
                        <li><a href="{{ route('support') }}" class="hover:underline">{{ __('Support') }}</a></li>
                    </ul>
                </div>
                <div>
                    <div class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Legal') }}</div>
                    <ul class="mt-2 space-y-1">
                        <li><a href="{{ route('privacy') }}" class="hover:underline">{{ __('Privacy policy') }}</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:underline">{{ __('Terms of service') }}</a></li>
                    </ul>
                </div>
            </div>
        </footer>
        @fluxScripts
    </body>
</html>
