<x-layouts::public :title="__('Free Zoom license audit')" :description="__('Connect your Zoom account, run one scan, and get a report of deactivated, pending, idle and unassigned licensed seats, with the annual cost. Free, no card.')">
    <div class="mx-auto max-w-3xl">
        <flux:badge color="green">{{ __('Free · takes about five minutes') }}</flux:badge>
        <h1 class="mt-4 text-4xl font-semibold tracking-tight">{{ __('Free Zoom license audit') }}</h1>
        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-300">{{ __('Find out how many Licensed seats your organization pays for that nobody uses, and what they cost per year. Connect as a Zoom admin, let SeatTrim run one scan, and keep the report.') }}</p>

        <ol class="mt-8 space-y-4">
            @foreach ([
                [__('Create a free SeatTrim account'), __('Email and password. No card.')],
                [__('Connect Zoom'), __('An admin-managed Zoom Marketplace app with read scopes for users, host reports and plan usage, plus one permission to change a user between Licensed and Basic (only used when you click).')],
                [__('Get the report'), __('Deactivated users still holding a license, pending invites, idle hosts by threshold, unassigned seats, and the annual cost at your seat price. Guardrails show who must not be touched and why.')],
                [__('Decide what to do'), __('Downgrade one at a time on the free plan, or upgrade for bulk actions and automation. Disconnect any time; all Zoom data is deleted immediately.')],
            ] as $i => [$heading, $text])
                <li class="flex gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">{{ $i + 1 }}</div>
                    <div><div class="font-semibold">{{ $heading }}</div><p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $text }}</p></div>
                </li>
            @endforeach
        </ol>

        <div class="mt-8 flex flex-wrap gap-3">
            <flux:button :href="route('register')" variant="primary">{{ __('Start the free audit') }}</flux:button>
            <flux:button :href="route('demo')" variant="filled">{{ __('See a sample report first') }}</flux:button>
        </div>

        <h2 class="mt-12 text-xl font-semibold">{{ __('What you need') }}</h2>
        <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-zinc-600 dark:text-zinc-300">
            <li>{{ __('A Zoom account on Pro, Business, Education or Enterprise. The host report is not available on free Zoom accounts.') }}</li>
            <li>{{ __('Owner or admin role in Zoom, with permission to view usage reports and manage users.') }}</li>
            <li>{{ __('Optional: your per-seat price and renewal date from Zoom Billing, for exact dollar figures.') }}</li>
        </ul>
    </div>
</x-layouts::public>
