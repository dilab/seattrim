<x-layouts::public
    :title="__('Reclaim unused Zoom licenses')"
    :description="__('SeatTrim finds Zoom seats nobody uses, shows the waste in dollars, and lets you downgrade users safely before your renewal.')"
    :jsonLd="['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'SeatTrim', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'description' => 'Reclaim unused Zoom licenses before your renewal.', 'url' => url('/')]">

    <section class="grid items-center gap-10 py-8 md:grid-cols-2">
        <div class="space-y-6">
            <flux:badge color="blue">{{ __('For Zoom admins with 50–2,000 seats') }}</flux:badge>
            <h1 class="text-4xl font-semibold tracking-tight md:text-5xl">{{ __('Stop paying for Zoom seats nobody uses.') }}</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-300">{{ __('SeatTrim connects to your Zoom account, scans it every night, and shows you the licensed users who are deactivated, never accepted their invite, or have not hosted a meeting in months. Downgrade them in one click, with guardrails, and cut the right number of seats at renewal.') }}</p>
            <div class="flex flex-wrap gap-3">
                <flux:button :href="route('register')" variant="primary">{{ __('Run a free audit') }}</flux:button>
                <flux:button :href="route('demo')" variant="filled">{{ __('Try the live demo') }}</flux:button>
            </div>
            <p class="text-sm text-zinc-500">{{ __('Free plan: unlimited scans and the full report. No card required.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="text-sm text-zinc-500">{{ __('Licensed seats you are paying for but not using') }}</div>
            <div class="mt-1 text-4xl font-semibold">$3,725<span class="text-lg font-normal text-zinc-500">/yr</span></div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"><div class="text-zinc-500">{{ __('Deactivated, still licensed') }}</div><div class="text-2xl font-semibold">5</div></div>
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"><div class="text-zinc-500">{{ __('Pending invites') }}</div><div class="text-2xl font-semibold">4</div></div>
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"><div class="text-zinc-500">{{ __('Idle for 90+ days') }}</div><div class="text-2xl font-semibold">11</div></div>
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"><div class="text-zinc-500">{{ __('Unassigned seats') }}</div><div class="text-2xl font-semibold">5</div></div>
            </div>
            <p class="mt-4 text-xs text-zinc-500">{{ __('Sample numbers from the demo account.') }}</p>
        </div>
    </section>

    <section class="py-12">
        <h2 class="text-2xl font-semibold">{{ __('Where the money goes') }}</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-4">
            @foreach ([
                [__('Deactivated users'), __('Someone left, an admin deactivated them, and the Licensed seat stayed assigned. Zoom keeps billing it.')],
                [__('Pending invites'), __('Invitations that were never accepted still reserve a Licensed seat.')],
                [__('Idle licensed users'), __('People who have not hosted a meeting in 30, 60, 90 or 180 days. Joining meetings does not need a license.')],
                [__('Unassigned seats'), __('Seats you bought that are not assigned to anyone at all.')],
            ] as [$heading, $text])
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="font-semibold">{{ $heading }}</div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="py-12">
        <h2 class="text-2xl font-semibold">{{ __('Safe by default') }}</h2>
        <ul class="mt-6 grid gap-3 md:grid-cols-2">
            @foreach ([
                __('Never touches admins, account owners or Zoom Rooms.'),
                __('Skips users with Zoom Phone, Webinar, Large Meeting or a Workplace bundle, because a downgrade would break those.'),
                __('Checks for upcoming scheduled meetings right before acting: a downgraded host would hit the 40-minute limit.'),
                __('Re-checks every user in Zoom immediately before any change and logs the outcome, with Zoom\'s tracking id.'),
                __('One-click restore while a seat is free. Exclusion lists by email, domain or group.'),
                __('Optional automation with a plain warning email and a "Keep my license" button, dry-run first.'),
            ] as $line)
                <li class="flex gap-2 text-sm"><span class="text-green-600">✓</span><span>{{ $line }}</span></li>
            @endforeach
        </ul>
    </section>

    <section class="rounded-2xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-950">
        <h2 class="text-xl font-semibold">{{ __('The honest part') }}</h2>
        <p class="mt-2 text-sm">{{ __('Downgrading a user does not reduce your Zoom bill by itself. Seats stay billable until you lower the license quantity on Zoom\'s Billing page, and annual plans do not refund mid-term. What SeatTrim does is free seats so new hires never trigger new purchases, and tell you the exact number to cut at renewal. It never claims you "saved $X today".') }}</p>
    </section>

    <section class="py-12 text-center">
        <h2 class="text-2xl font-semibold">{{ __('See your own numbers in five minutes') }}</h2>
        <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ __('Connect as a Zoom admin, run one scan, keep the report. Free.') }}</p>
        <div class="mt-6 flex justify-center gap-3">
            <flux:button :href="route('register')" variant="primary">{{ __('Run a free audit') }}</flux:button>
            <flux:button :href="route('pricing')" variant="ghost">{{ __('See pricing') }}</flux:button>
        </div>
    </section>
</x-layouts::public>
