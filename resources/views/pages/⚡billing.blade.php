<?php

use App\Billing\Plan;
use App\Billing\PlanResolver;
use App\Billing\Plans;
use App\Models\Organization;
use App\Support\Money;
use App\Tenancy\Tenancy;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component {
    public function mount(): void
    {
        $this->authorize('view', $this->organization());
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    public function current(): Plan
    {
        return PlanResolver::current($this->organization());
    }

    public function seats(): int
    {
        return PlanResolver::detectedSeats($this->organization());
    }

    public function required(): ?Plan
    {
        return PlanResolver::required($this->organization());
    }

    public function stripeConfigured(): bool
    {
        return config('cashier.key') !== null && config('cashier.secret') !== null;
    }
}; ?>

<div class="space-y-6">
    @php($org = $this->organization())
    @php($current = $this->current())
    @php($seats = $this->seats())
    @php($required = $this->required())
    @php($isOwner = auth()->user()->isOwnerOf($org))

    <div>
        <flux:heading size="xl">{{ __('Billing') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Annual plans, priced by the licensed seats SeatTrim finds in your Zoom account. The free plan never limits scans or the report.') }}</flux:text>
    </div>

    @if (request('checkout') === 'success')
        <flux:callout icon="check-circle" variant="success"><flux:callout.text>{{ __('Thank you! Your subscription is active as soon as Stripe confirms the payment.') }}</flux:callout.text></flux:callout>
    @elseif (request('checkout') === 'cancelled')
        <flux:callout icon="information-circle" variant="secondary"><flux:callout.text>{{ __('Checkout cancelled. Nothing was charged.') }}</flux:callout.text></flux:callout>
    @endif
    @if (session('billing.error'))
        <flux:callout icon="exclamation-triangle" variant="danger"><flux:callout.text>{{ session('billing.error') }}</flux:callout.text></flux:callout>
    @endif
    @if (session('billing.success'))
        <flux:callout icon="check-circle" variant="success"><flux:callout.text>{{ session('billing.success') }}</flux:callout.text></flux:callout>
    @endif

    <flux:card class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Current plan: :plan', ['plan' => $current->name]) }}</flux:heading>
                <flux:text>{{ __(':n licensed seats detected in the latest scan.', ['n' => $seats]) }}
                    @if ($required && $required->key !== $current->key && ! $current->isFree() && ! $current->fitsSeats($seats))
                        {{ __('Your account has outgrown :plan (up to :limit seats).', ['plan' => $current->name, 'limit' => $current->seatLimit]) }}
                    @endif
                </flux:text>
            </div>
            @if ($isOwner && $org->hasStripeId())
                <flux:button :href="route('billing.portal')" variant="filled" icon="credit-card">{{ __('Manage billing') }}</flux:button>
            @endif
        </div>
        @if (PlanResolver::overLimit($org))
            <flux:callout icon="exclamation-triangle" :variant="PlanResolver::graceExpired($org) ? 'danger' : 'warning'">
                <flux:callout.text>
                    @if (PlanResolver::graceExpired($org))
                        {{ __('The 14-day grace period ended on :date. Bulk actions, automation, digests, reminders and export are paused until you upgrade. Scans and the report keep working.', ['date' => PlanResolver::graceEndsAt($org)?->toFormattedDateString()]) }}
                    @else
                        {{ __('You have until :date to move to a plan that covers :n seats. Until then everything keeps working.', ['date' => PlanResolver::graceEndsAt($org)?->toFormattedDateString(), 'n' => $seats]) }}
                    @endif
                </flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <div class="grid gap-4 md:grid-cols-4">
        @foreach (Plans::all() as $plan)
            <flux:card class="flex flex-col gap-3 {{ $plan->key === $current->key ? 'ring-2 ring-blue-500' : '' }}" data-test="plan-{{ $plan->key }}">
                <div>
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    <div class="mt-1 text-2xl font-semibold">{{ $plan->isFree() ? __('Free') : Money::format($plan->priceCents, 'USD').'/'.__('yr') }}</div>
                    <flux:text class="text-xs">{{ $plan->seatLimit === null ? __('any number of seats') : __('up to :n licensed seats', ['n' => $plan->seatLimit]) }}</flux:text>
                </div>
                <ul class="flex-1 space-y-1 text-sm">
                    <li>✓ {{ __('Connect, daily scan, full report') }}</li>
                    <li>✓ {{ __('Downgrade / restore one at a time') }}</li>
                    @if ($plan->isFree())
                        <li>✓ {{ __('1 admin') }}</li>
                    @else
                        <li>✓ {{ __('Bulk actions') }}</li>
                        <li>✓ {{ __('Automation with warning emails') }}</li>
                        <li>✓ {{ __('Weekly digest, renewal reminders') }}</li>
                        <li>✓ {{ __('CSV export, multiple admins') }}</li>
                    @endif
                </ul>
                @if ($plan->key === $current->key)
                    <flux:badge color="blue">{{ __('Current') }}</flux:badge>
                @elseif (! $plan->isFree() && $isOwner)
                    @if ($this->stripeConfigured() && $plan->stripePrice)
                        <form method="POST" action="{{ route('billing.checkout', $plan->key) }}">
                            @csrf
                            <flux:button type="submit" variant="primary" class="w-full" :disabled="! $plan->fitsSeats($seats)">{{ $plan->fitsSeats($seats) ? __('Choose :plan', ['plan' => $plan->name]) : __('Too small for :n seats', ['n' => $seats]) }}</flux:button>
                        </form>
                    @else
                        <flux:tooltip :content="__('Stripe is not configured in this environment.')"><flux:button variant="primary" class="w-full" disabled>{{ __('Choose :plan', ['plan' => $plan->name]) }}</flux:button></flux:tooltip>
                    @endif
                @elseif (! $plan->isFree())
                    <flux:text class="text-xs">{{ __('Only an owner can change the plan.') }}</flux:text>
                @endif
            </flux:card>
        @endforeach
    </div>

    <flux:text class="text-xs">{{ __('Prices in USD, billed yearly, cancel any time (access continues to the end of the period). Invoices and VAT details are handled by Stripe. SeatTrim is a product of StaticMaker Pte Ltd, Singapore.') }}</flux:text>
</div>
