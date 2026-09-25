<x-layouts::public :title="__('Pricing')" :description="__('SeatTrim pricing: free audit and report, paid plans from $290 per year by licensed seats. Annual billing, cancel any time.')">
    <h1 class="text-4xl font-semibold tracking-tight">{{ __('Simple annual pricing') }}</h1>
    <p class="mt-3 max-w-2xl text-zinc-600 dark:text-zinc-300">{{ __('Priced by the licensed seats SeatTrim finds in your Zoom account. Billed yearly, because that is when your Zoom seat count actually changes. The free plan never limits scans or the report.') }}</p>

    <div class="mt-10 grid gap-4 md:grid-cols-4">
        @foreach (App\Billing\Plans::all() as $plan)
            <div class="flex flex-col rounded-2xl border p-6 {{ $plan->key === 'growth' ? 'border-blue-500' : 'border-zinc-200 dark:border-zinc-700' }}">
                <div class="text-lg font-semibold">{{ $plan->name }}</div>
                <div class="mt-2 text-3xl font-semibold">{{ $plan->isFree() ? __('Free') : App\Support\Money::format($plan->priceCents, 'USD') }}<span class="text-base font-normal text-zinc-500">{{ $plan->isFree() ? '' : '/'.__('yr') }}</span></div>
                <div class="mt-1 text-sm text-zinc-500">{{ $plan->seatLimit === null ? __('any account size') : __('up to :n licensed seats', ['n' => $plan->seatLimit]) }}</div>
                <ul class="mt-4 flex-1 space-y-2 text-sm">
                    <li>✓ {{ __('Connect, daily scan, full report') }}</li>
                    <li>✓ {{ __('Downgrade and restore one user at a time') }}</li>
                    @if ($plan->isFree())
                        <li>✓ {{ __('1 admin') }}</li>
                    @else
                        <li>✓ {{ __('Bulk downgrade and restore') }}</li>
                        <li>✓ {{ __('Automation: warning email, keep-my-license link, dry run') }}</li>
                        <li>✓ {{ __('Weekly digest and renewal reminders') }}</li>
                        <li>✓ {{ __('CSV export of the audit log') }}</li>
                        <li>✓ {{ __('Multiple admins and viewers') }}</li>
                    @endif
                </ul>
                <flux:button :href="route('register')" class="mt-6" :variant="$plan->key === 'growth' ? 'primary' : 'filled'">{{ $plan->isFree() ? __('Start free') : __('Start with :plan', ['plan' => $plan->name]) }}</flux:button>
            </div>
        @endforeach
    </div>

    <div class="mt-12 grid gap-6 md:grid-cols-2">
        <div>
            <h2 class="font-semibold">{{ __('What happens if my account grows past the tier?') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('You get a banner and 14 days to move up. After that, paid features pause until you do. Scans and the report keep working.') }}</p>
        </div>
        <div>
            <h2 class="font-semibold">{{ __('Why annual only?') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Zoom renews yearly for most organizations, and the seat count only changes then. An annual plan keeps SeatTrim watching in the months that matter instead of being switched on for one clean-up.') }}</p>
        </div>
        <div>
            <h2 class="font-semibold">{{ __('Does SeatTrim change my Zoom bill?') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('No. It frees seats and tells you the number to cut. You change the quantity in Zoom Billing, usually at renewal.') }}</p>
        </div>
        <div>
            <h2 class="font-semibold">{{ __('Who is behind it?') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ __('StaticMaker Pte Ltd, Singapore. Payments are processed by Stripe; invoices come from Stripe.') }}</p>
        </div>
    </div>
</x-layouts::public>
