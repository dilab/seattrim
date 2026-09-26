<x-layouts::public :title="__('Pricing')" :description="__('SeatTrim pricing: free audit and report, paid plans from $290 per year by licensed seats. Annual billing, cancel any time.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Pricing')" :heading="__('Priced by the seats you have.')" :muted="__('Paid once a year, like Zoom.')" :intro="__('Tiers follow the licensed seats SeatTrim finds in your latest scan. The free plan never limits scans or the report.')" />
    </x-slot>

    <section class="grid gap-6 md:grid-cols-4">
        @foreach (App\Billing\Plans::all() as $plan)
            <div class="flex flex-col gap-4 rounded-3xl bg-white p-6 shadow-card {{ $plan->key === 'growth' ? 'ring-2 ring-brand-500' : '' }}">
                <div class="flex items-center justify-between">
                    <div class="text-[15px] font-medium">{{ $plan->name }}</div>
                    @if ($plan->key === 'growth')<x-public.pill tone="brand">{{ __('Most chosen') }}</x-public.pill>@endif
                </div>
                <div class="text-[40px] leading-none tracking-tight">{{ $plan->isFree() ? __('Free') : App\Support\Money::format($plan->priceCents, 'USD') }}@if (! $plan->isFree())<span class="ml-1 text-[16px] text-ink-400">/{{ __('yr') }}</span>@endif</div>
                <div class="text-[14px] text-ink-500">{{ $plan->seatLimit === null ? __('any account size') : __('up to :n licensed seats', ['n' => $plan->seatLimit]) }}</div>
                <ul class="flex flex-1 flex-col gap-2 text-[14px] leading-[1.5] text-ink-700">
                    <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Connect, daily scan, full report') }}</li>
                    <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Downgrade and restore one user at a time') }}</li>
                    @if ($plan->isFree())
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('1 admin') }}</li>
                    @else
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Bulk downgrade and restore') }}</li>
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Automation: warning email, keep-my-license link, dry run') }}</li>
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Weekly digest and renewal reminders') }}</li>
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('CSV export of the audit log') }}</li>
                        <li class="flex gap-2"><span class="text-brand-500">—</span>{{ __('Multiple admins and viewers') }}</li>
                    @endif
                </ul>
                <a href="{{ route('register') }}" class="inline-flex h-11 items-center justify-center rounded-full text-sm font-medium transition active:scale-[.98] {{ $plan->key === 'growth' ? 'bg-ink-900 text-white hover:bg-ink-700' : 'bg-brand-50 text-brand-600 hover:bg-brand-100' }}">{{ $plan->isFree() ? __('Start free') : '↳ '.__('Start with :plan', ['plan' => $plan->name]) }}</a>
            </div>
        @endforeach
    </section>

    <section class="grid gap-6 rounded-3xl bg-white p-6 shadow-card sm:p-8 md:grid-cols-2">
        @foreach ([
            [__('What happens if my account grows past the tier?'), __('You get a banner and 14 days to move up. After that, paid features pause until you do. Scans and the report keep working.')],
            [__('Why annual only?'), __('Zoom renews yearly for most organizations, and the seat count only changes then. An annual plan keeps SeatTrim watching in the months that matter instead of being switched on for one clean-up.')],
            [__('Does SeatTrim change my Zoom bill?'), __('No. It frees seats and tells you the number to cut. You change the quantity in Zoom Billing, usually at renewal.')],
            [__('Who is behind it?'), __('StaticMaker Pte Ltd, Singapore. Payments and invoices are handled by Stripe.')],
        ] as [$q, $a])
            <div class="flex flex-col gap-2">
                <div class="text-[15px] font-medium">{{ $q }}</div>
                <p class="max-w-prose text-[15px] leading-[1.6] text-ink-700">{{ $a }}</p>
            </div>
        @endforeach
    </section>
</x-layouts::public>
