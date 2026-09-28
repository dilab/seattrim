<x-layouts::public :title="__('Free Zoom license audit')" :description="__('Connect your Zoom account, run one scan, and get a report of idle, pending and unassigned licensed seats, with the annual cost. Free, no card.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Free · about five minutes')" :heading="__('Free Zoom license audit.')" :muted="__('Keep the report.')" :intro="__('Find out how many Licensed seats your organization pays for that nobody uses, and what they cost per year. Connect as a Zoom admin, let SeatTrim run one scan, and keep the report.')">
            <x-slot name="actions">
                <x-public.field-button :href="route('register')">↳ {{ __('Start the free audit') }}</x-public.field-button>
                <x-public.field-button :href="route('demo')" variant="outline">{{ __('See a sample report first') }}</x-public.field-button>
            </x-slot>
            <x-slot name="aside">
                <div class="text-[14px] font-medium text-ink-900">{{ __('What you need') }}</div>
                <ul class="flex flex-col gap-2.5 text-[14px] leading-[1.5] text-ink-700">
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
                [__('Get the report'), __('Idle hosts by threshold, pending invites, unassigned seats (including those released by leavers), any deactivated user still holding a license, and the annual cost at your seat price. Guardrails show who must not be touched and why.')],
                [__('Decide what to do'), __('Downgrade one at a time on the free plan, or upgrade for bulk actions and automation. Disconnect any time; all Zoom data is deleted immediately.')],
            ] as $i => [$heading, $text])
                <li class="flex flex-col gap-3">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-[13px] font-medium text-brand-600">{{ $i + 1 }}</span>
                    <div class="text-[15px] font-medium">{{ $heading }}</div>
                    <p class="text-[15px] leading-[1.6] text-ink-700">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-layouts::public>
