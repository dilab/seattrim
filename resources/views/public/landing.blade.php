<x-layouts::public
    :title="__('Reclaim unused Zoom licenses')"
    :description="__('SeatTrim finds the Zoom licenses nobody uses and downgrades them safely, so you buy fewer seats and pay for fewer at renewal.')"
    :jsonLd="['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'SeatTrim', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'description' => 'Reclaim unused Zoom licenses before your renewal.', 'url' => url('/')]">

    <x-slot name="hero">
        <div class="flex max-w-2xl flex-col items-center gap-5 pt-6 text-center">
            <h1 class="text-[40px] font-normal leading-[1.05] tracking-[-0.02em] text-white sm:text-[48px] md:text-[56px]" style="text-wrap:balance">{{ __('Stop paying for seats no one is sitting in.') }}</h1>
            <p class="max-w-lg text-[16px] leading-[1.6] text-white">{{ __('SeatTrim finds the Zoom licenses nobody uses and downgrades them safely, so you buy fewer seats and pay for fewer at renewal.') }}</p>
            <div class="flex flex-wrap justify-center gap-3 pt-1">
                <a href="{{ route('register') }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-sm font-medium text-ink-900 shadow-card transition hover:bg-brand-50 active:scale-[.98]">↳ {{ __('Scan my Zoom account') }}</a>
                <a href="{{ route('demo') }}" class="inline-flex h-11 items-center rounded-full border border-white/60 px-5 text-sm font-medium text-white transition hover:bg-white/15 active:scale-[.98]">{{ __('See a sample report') }}</a>
            </div>
            <p class="text-[13px] text-white">{{ __('Free plan: unlimited scans and the full report. No card.') }}</p>
        </div>

        {{-- Product cards tilted into the fold, floating status pills around them (numbers from the demo account). --}}
        <div class="relative mt-4 h-[300px] w-full max-w-3xl">
            <x-public.pill tone="dark" class="absolute left-1/2 top-0 -translate-x-1/2 shadow-card">{{ __('Zoom synced') }}</x-public.pill>
            <x-public.pill tone="blush" class="absolute left-[6%] top-10 shadow-card">{{ __('Scanning 69 users') }}</x-public.pill>
            <x-public.pill tone="mint" class="absolute right-[6%] top-10 shadow-card"><span class="flex size-3.5 items-center justify-center rounded-full bg-mint-500 text-[9px] text-white">✓</span>{{ __('25 seats reclaimable') }}</x-public.pill>

            <div class="absolute left-1/2 top-16 z-10 flex w-[340px] max-w-[92vw] -translate-x-1/2 flex-col gap-3 rounded-2xl bg-white p-4 shadow-float">
                <div class="flex items-center justify-between text-[13px]"><x-app-wordmark /><span class="text-xs text-ink-400">{{ __('Reviewing seats…') }}</span></div>
                <div class="flex items-center justify-between rounded-xl border border-ink-100 px-3 py-2.5 text-[13px]"><div><div class="text-ink-900">Former Staff 3</div><div class="text-[11px] text-ink-500">{{ __('Deactivated · still Licensed') }}</div></div><span class="inline-flex h-6 items-center rounded-full bg-coral-100 px-2.5 text-[11px] font-medium text-coral-700">{{ __('Unused') }}</span></div>
                <div class="flex items-center justify-between rounded-xl border border-brand-100 bg-brand-50 px-3 py-2.5 text-[13px]"><div><div class="text-ink-900">Never Hosted 2</div><div class="text-[11px] text-ink-500">{{ __('Licensed · no meetings in 180 days') }}</div></div><span class="inline-flex h-6 items-center rounded-full bg-brand-100 px-2.5 text-[11px] font-medium text-brand-600">{{ __('Eligible') }}</span></div>
                <div class="flex items-center justify-between rounded-xl border border-ink-100 px-3 py-2.5 text-[13px] opacity-60"><div><div class="text-ink-900">Idle Thirty 1</div><div class="text-[11px] text-ink-500">{{ __('Licensed · last meeting 36 days ago') }}</div></div><span class="inline-flex h-6 items-center rounded-full bg-blush-100 px-2.5 text-[11px] font-medium text-blush-700">{{ __('Idle') }}</span></div>
            </div>
            <div class="absolute left-0 top-28 hidden w-[220px] origin-bottom-right -rotate-6 flex-col gap-2 rounded-2xl bg-white p-4 shadow-float md:flex">
                <div class="text-xs text-ink-500">{{ __('Reclaimable at renewal') }}</div><div class="text-[32px] leading-none tracking-tight">$3,725<span class="ml-1 text-sm text-ink-400">/yr</span></div><div class="text-[11px] text-mint-700">{{ __('25 seats → reduce to 42') }}</div>
            </div>
            <div class="absolute right-0 top-28 hidden w-[230px] origin-bottom-left rotate-6 flex-col gap-2 rounded-2xl bg-white p-4 shadow-float md:flex">
                <div class="flex items-center gap-2"><x-app-logo-icon class="size-6 text-brand-500" /><span class="text-[13px] font-medium">{{ __('Ready to approve') }}</span></div>
                <p class="text-[11px] leading-relaxed text-ink-500">{{ __('Nothing changes until you say so. Everyone keeps Basic and can be restored in a click.') }}</p>
                <span class="inline-flex h-8 w-fit items-center rounded-full bg-ink-900 px-3 text-xs font-medium text-white">↳ {{ __('Approve 16') }}</span>
            </div>
        </div>
    </x-slot>

    {{-- Why --}}
    <section class="flex flex-col items-center gap-6 rounded-3xl bg-white p-6 text-center shadow-card sm:p-8">
        <x-public.section-label>{{ __('Why SeatTrim') }}</x-public.section-label>
        <h2 class="max-w-3xl text-[32px] font-light leading-[1.15] tracking-[-0.015em] md:text-[40px]" style="text-wrap:balance">{{ __('Zoom keeps billing seats nobody uses.') }} <span class="text-ink-500">{{ __('SeatTrim finds them, quietly, before renewal.') }}</span></h2>
        <div class="grid w-full max-w-3xl gap-3 sm:grid-cols-2 md:grid-cols-4">
            @foreach ([
                [__('Deactivated'), __('Someone left, an admin deactivated them, and the Licensed seat stayed assigned.'), 'coral'],
                [__('Pending invites'), __('Invitations never accepted still reserve a seat.'), 'blush'],
                [__('Idle hosts'), __('Licensed, but no meeting hosted in 30, 60, 90 or 180 days. Joining needs no license.'), 'brand'],
                [__('Unassigned'), __('Seats you bought and never gave to anyone.'), 'ink'],
            ] as [$heading, $text, $tone])
                <div class="flex flex-col gap-2 rounded-2xl border border-ink-100 p-4 text-left">
                    <x-public.pill :tone="$tone">{{ $heading }}</x-public.pill>
                    <p class="text-[15px] leading-[1.6] text-ink-700">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="flex flex-col gap-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
        <div class="grid items-end gap-8 md:grid-cols-2">
            <div class="flex flex-col gap-4">
                <x-public.section-label>{{ __('Features') }}</x-public.section-label>
                <h2 class="text-[28px] font-light leading-[1.2] tracking-tight">{{ __('Everything SeatTrim watches,') }}<br>{{ __('so you don’t have to.') }}</h2>
            </div>
            <p class="max-w-md text-[15px] leading-[1.6] text-ink-500 md:justify-self-end md:text-right">{{ __('Three quiet jobs running in the background of your Zoom account: from the data you already have to the invoice you would rather not pay.') }}</p>
        </div>
        <div class="grid gap-6 md:grid-cols-3">
            <div class="flex flex-col gap-4">
                <div class="flex h-44 items-center justify-center rounded-3xl border border-ink-100 p-4" style="background:linear-gradient(135deg,#fbe3f2,#ffffff 50%,#f6c3e3)">
                    <div class="w-full max-w-[220px] -rotate-3 rounded-2xl bg-white p-3 shadow-float">
                        <div class="text-[11px] text-ink-500">{{ __('Last hosted') }}</div>
                        <div class="mt-1 grid grid-cols-3 gap-1 text-center text-[11px]">
                            <div class="rounded-lg bg-ink-100 py-1"><div class="text-ink-400">0-30d</div><div class="font-medium">0</div></div>
                            <div class="rounded-lg bg-ink-100 py-1"><div class="text-ink-400">30-60d</div><div class="font-medium">0</div></div>
                            <div class="rounded-lg bg-ink-100 py-1"><div class="text-ink-400">60-90d</div><div class="font-medium">0</div></div>
                        </div>
                    </div>
                </div>
                <div class="text-center"><div class="text-[15px] font-medium">{{ __('Sees what is idle') }}</div><p class="mt-1 text-[15px] leading-[1.6] text-ink-500">{{ __('Every seat, by meetings hosted per 30-day window from Zoom’s own host report. No spreadsheets.') }}</p></div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex h-44 items-center justify-center rounded-3xl border border-ink-100 p-4" style="background:linear-gradient(135deg,#edf3ff,#ffffff 50%,#dbe7ff)">
                    <div class="w-full max-w-[220px] rotate-2 rounded-2xl bg-white p-3 text-[12px] shadow-float">
                        <div class="flex items-center justify-between py-1"><span class="text-ink-700">{{ __('Has Zoom Phone') }}</span><x-public.pill tone="ink" class="h-6 text-[11px]">{{ __('Protected') }}</x-public.pill></div>
                        <div class="flex items-center justify-between py-1"><span class="text-ink-700">{{ __('Meeting on Friday') }}</span><x-public.pill tone="ink" class="h-6 text-[11px]">{{ __('Protected') }}</x-public.pill></div>
                        <div class="flex items-center justify-between py-1"><span class="text-ink-700">{{ __('Never hosted') }}</span><x-public.pill tone="brand" class="h-6 text-[11px]">{{ __('Downgrade') }}</x-public.pill></div>
                    </div>
                </div>
                <div class="text-center"><div class="text-[15px] font-medium">{{ __('Downgrades safely') }}</div><p class="mt-1 text-[15px] leading-[1.6] text-ink-500">{{ __('Admins, rooms, bundles, add-ons and upcoming meetings are protected. Re-checked in Zoom before every change. One-click restore.') }}</p></div>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex h-44 items-center justify-center rounded-3xl border border-ink-100 p-4" style="background:linear-gradient(135deg,#eceefc,#ffffff 50%,#d9ddfb)">
                    <div class="w-full max-w-[220px] -rotate-2 rounded-2xl bg-white p-3 shadow-float">
                        <div class="text-[11px] text-ink-500">{{ __('At renewal, reduce to') }}</div>
                        <div class="text-[28px] leading-none tracking-tight">42<span class="ml-1 text-sm text-ink-400">{{ __('seats') }}</span></div>
                        <div class="mt-1 text-[11px] text-coral-700">{{ __('Renewal in 52 days') }}</div>
                    </div>
                </div>
                <div class="text-center"><div class="text-[15px] font-medium">{{ __('Pays off at renewal') }}</div><p class="mt-1 text-[15px] leading-[1.6] text-ink-500">{{ __('A right-sizing target and reminders 60, 30 and 7 days before the contract renews, ready for finance.') }}</p></div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="flex flex-col gap-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
        <x-public.section-label>{{ __('How it works') }}</x-public.section-label>
        <div class="grid gap-10 md:grid-cols-[200px_1fr]">
            <ol class="m-0 flex list-none flex-col gap-3 border-l border-ink-200 p-0 text-[13px]">
                <li class="-ml-px border-l-2 border-coral-500 pl-4 font-medium text-ink-900">{{ __('Connect Zoom') }}</li>
                <li class="pl-4 text-ink-500">{{ __('Set your rules') }}</li>
                <li class="pl-4 text-ink-500">{{ __('SeatTrim scans nightly') }}</li>
                <li class="pl-4 text-ink-500">{{ __('Review the report') }}</li>
                <li class="pl-4 text-ink-500">{{ __('Approve and right-size') }}</li>
            </ol>
            <div class="flex flex-col gap-6">
                <p class="max-w-xl text-[18px] font-light leading-relaxed text-ink-900">{{ __('Sign in with your Zoom admin account. SeatTrim reads license and hosting activity only. It never joins, records or changes anything until you approve.') }}</p>
                <div class="grid gap-3 rounded-3xl border border-ink-200 bg-ink-100 p-4 text-[13px] sm:grid-cols-2">
                    <div class="rounded-2xl bg-white p-4 shadow-card"><div class="text-[11px] text-ink-500">{{ __('Reads') }}</div><p class="mt-1 text-ink-700">{{ __('Users in every status, host reports per 30-day window, plan usage, add-on flags of candidates.') }}</p></div>
                    <div class="rounded-2xl bg-white p-4 shadow-card"><div class="text-[11px] text-ink-500">{{ __('Never reads') }}</div><p class="mt-1 text-ink-700">{{ __('Meeting content, recordings, chat or participant lists.') }}</p></div>
                </div>
            </div>
        </div>
    </section>

    {{-- The honest part --}}
    <section class="grid gap-6 rounded-3xl border border-ink-200 bg-ink-100 p-6 sm:p-8 md:grid-cols-[1fr_1.4fr]">
        <div class="flex flex-col gap-3">
            <x-public.section-label>{{ __('The honest part') }}</x-public.section-label>
            <h2 class="text-[28px] font-light leading-[1.2] tracking-tight">{{ __('A downgrade frees a seat.') }} <span class="text-ink-500">{{ __('Renewal changes the bill.') }}</span></h2>
        </div>
        <p class="max-w-2xl text-[16px] leading-[1.6] text-ink-700">{{ __('Downgrading a user does not reduce your Zoom bill by itself. Seats stay billable until you lower the license quantity on Zoom’s Billing page, and annual plans do not refund mid-term. SeatTrim frees seats so new hires never trigger new purchases, and tells you the exact number to cut at renewal. It never claims you saved money today.') }}</p>
    </section>

    {{-- Closing CTA --}}
    <section class="relative flex flex-col items-center gap-5 overflow-hidden rounded-3xl bg-white p-8 text-center shadow-card md:p-12">
        <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image:radial-gradient(#b9cfff 1px, transparent 1.3px);background-size:9px 9px;mask-image:radial-gradient(ellipse 60% 70% at 50% 100%, #000 0%, transparent 100%);-webkit-mask-image:radial-gradient(ellipse 60% 70% at 50% 100%, #000 0%, transparent 100%)"></div>
        <x-public.pill tone="mint" class="absolute left-6 top-10 hidden shadow-card lg:inline-flex">{{ __('16 seats eligible') }}</x-public.pill>
        <x-public.pill tone="blush" class="absolute right-6 top-14 hidden shadow-card lg:inline-flex">{{ __('Renewal drafted') }}</x-public.pill>
        <x-public.pill tone="brand" class="absolute bottom-24 left-16 hidden shadow-card lg:inline-flex">{{ __('$3,725 /yr reclaimable') }}</x-public.pill>
        <h2 class="relative max-w-md text-[40px] font-medium leading-[1.05] tracking-[-0.02em] md:text-[48px]" style="text-wrap:balance">{{ __('Your next renewal is already smaller.') }}</h2>
        <p class="relative max-w-sm text-[15px] leading-[1.6] text-ink-500">{{ __('Connect Zoom, run one scan, and see the seats you do not need. Free.') }}</p>
        <a href="{{ route('register') }}" class="relative inline-flex h-11 items-center rounded-full border border-ink-200 bg-white px-5 text-sm font-medium text-ink-900 shadow-card hover:border-ink-400">↳ {{ __('Try SeatTrim') }}</a>
    </section>
</x-layouts::public>
