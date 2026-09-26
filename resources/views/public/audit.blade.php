<x-layouts::public :title="__('Free Zoom license audit')" :description="__('Connect your Zoom account, run one scan, and get a report of deactivated, pending, idle and unassigned licensed seats, with the annual cost. Free, no card.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Free · about five minutes')" :heading="__('Free Zoom license audit.')" :muted="__('Keep the report.')" :intro="__('Find out how many Licensed seats your organization pays for that nobody uses, and what they cost per year. Connect as a Zoom admin, let SeatTrim run one scan, and keep the report.')">
            <x-slot name="actions">
                <a href="{{ route('register') }}" class="inline-flex h-11 items-center rounded-full bg-white px-5 text-sm font-medium text-ink-900 shadow-card transition hover:bg-brand-50 active:scale-[.98]">↳ {{ __('Start the free audit') }}</a>
                <a href="{{ route('demo') }}" class="inline-flex h-11 items-center rounded-full border border-white/60 px-5 text-sm font-medium text-white transition hover:bg-white/15 active:scale-[.98]">{{ __('See a sample report first') }}</a>
            </x-slot>
            <x-slot name="aside">
                <div class="text-[13px] font-medium text-ink-900">{{ __('What you need') }}</div>
                <ul class="flex flex-col gap-2.5 text-[13px] leading-relaxed text-ink-700">
                    <li class="flex gap-3"><span class="text-brand-500">—</span>{{ __('A Zoom account on Pro, Business, Education or Enterprise. The host report is not available on free Zoom accounts.') }}</li>
                    <li class="flex gap-3"><span class="text-brand-500">—</span>{{ __('Owner or admin role in Zoom, with permission to view usage reports and manage users.') }}</li>
                    <li class="flex gap-3"><span class="text-brand-500">—</span>{{ __('Optional: your per-seat price and renewal date from Zoom Billing, for exact dollar figures.') }}</li>
                </ul>
            </x-slot>
        </x-public.page-hero>
    </x-slot>

    <section class="flex flex-col gap-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
        <x-public.section-label>{{ __('How it works') }}</x-public.section-label>
        <ol class="grid gap-6 md:grid-cols-4">
            @foreach ([
                [__('Create a free account'), __('Email and password. No card.')],
                [__('Connect Zoom'), __('An admin-managed Marketplace app with read scopes for users, host reports and plan usage, plus one permission to change a user between Licensed and Basic, only used when you click.')],
                [__('Get the report'), __('Deactivated users still holding a license, pending invites, idle hosts by threshold, unassigned seats, and the annual cost at your seat price. Guardrails show who must not be touched and why.')],
                [__('Decide what to do'), __('Downgrade one at a time on the free plan, or upgrade for bulk actions and automation. Disconnect any time; all Zoom data is deleted immediately.')],
            ] as $i => [$heading, $text])
                <li class="flex flex-col gap-3">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-[13px] font-medium text-brand-600">{{ $i + 1 }}</span>
                    <div class="text-[15px] font-medium">{{ $heading }}</div>
                    <p class="text-[13px] leading-relaxed text-ink-500">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-layouts::public>
