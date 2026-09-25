<?php

use App\Models\Organization;
use App\Scan\ScanSettings;
use App\Support\Money;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organization settings')] class extends Component {
    public string $name = '';
    public string $timezone = 'UTC';
    public string $seat_price = '149.00';
    public ?string $renewal_date = null;
    public ?string $billing_cycle = 'annual';
    public string $currency = 'USD';
    public int $threshold_days = 90;
    public int $new_hire_days = 30;

    public function mount(): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('view', $organization);

        $this->name = $organization->name;
        $this->timezone = $organization->timezone;
        $this->seat_price = number_format($organization->seat_price_cents / 100, 2, '.', '');
        $this->renewal_date = $organization->renewal_date?->toDateString();
        $this->billing_cycle = $organization->billing_cycle;
        $this->currency = (string) $organization->setting('currency', 'USD');
        $settings = ScanSettings::for($organization);
        $this->threshold_days = $settings->thresholdDays;
        $this->new_hire_days = $settings->newHireDays;
    }

    public function save(): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('update', $organization);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'seat_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'renewal_date' => ['nullable', 'date'],
            'billing_cycle' => ['nullable', Rule::in(Organization::BILLING_CYCLES)],
            'currency' => ['required', Rule::in(Money::CURRENCIES)],
            'threshold_days' => ['required', Rule::in(ScanSettings::THRESHOLD_CHOICES)],
            'new_hire_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $organization->setSetting('currency', $validated['currency']);
        $organization->setSetting('scan.threshold_days', (int) $validated['threshold_days']);
        $organization->setSetting('scan.new_hire_days', (int) $validated['new_hire_days']);

        $organization->fill([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'seat_price_cents' => (int) round(((float) $validated['seat_price']) * 100),
            'renewal_date' => $validated['renewal_date'] ?: null,
            'billing_cycle' => $validated['billing_cycle'] ?: null,
        ])->save();

        Flux::toast(variant: 'success', text: __('Organization updated.'));
    }

    public function canManage(): bool
    {
        return auth()->user()->canManage(app(Tenancy::class)->currentOrFail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Organization settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Organization')" :subheading="__('Name, timezone and what you pay Zoom per seat')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Organization name')" type="text" required :disabled="! $this->canManage()" />

            <flux:select wire:model="timezone" :label="__('Timezone')" :description="__('Daily scans run at 03:00 in this timezone.')" :disabled="! $this->canManage()">
                @foreach (DateTimeZone::listIdentifiers() as $tz)
                    <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:separator variant="subtle" />

            <flux:heading size="lg">{{ __('Zoom billing') }}</flux:heading>
            <flux:text>{{ __('Used only to show waste in dollars and to build the renewal reminder. Nothing here changes your Zoom plan.') }}</flux:text>

            <flux:select wire:model="billing_cycle" :label="__('Billing cycle')" :disabled="! $this->canManage()">
                <flux:select.option value="annual">{{ __('Annual') }}</flux:select.option>
                <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="seat_price" :label="__('Price per licensed seat')" :description="__('Per seat, per billing period, in your Zoom invoice currency. Zoom Pro list price is about 149 per year or 15.99 per month.')" type="number" step="0.01" min="0" required :disabled="! $this->canManage()" />

            <flux:select wire:model="currency" :label="__('Currency')" :disabled="! $this->canManage()">
                @foreach (Money::CURRENCIES as $code)
                    <flux:select.option value="{{ $code }}">{{ $code }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="renewal_date" :label="__('Zoom renewal date')" :description="__('Find it under Zoom web portal > Account Management > Billing.')" type="date" :disabled="! $this->canManage()" />

            <flux:separator variant="subtle" />

            <flux:heading size="lg">{{ __('Scan rules') }}</flux:heading>

            <flux:select wire:model="threshold_days" :label="__('Idle threshold')" :description="__('A licensed user who has not hosted a meeting within this many days is idle. Zoom keeps about six months of host reports, so 180 is the maximum.')" :disabled="! $this->canManage()">
                @foreach (ScanSettings::THRESHOLD_CHOICES as $days)
                    <flux:select.option value="{{ $days }}">{{ $days }} {{ __('days') }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="new_hire_days" :label="__('Protect new accounts for (days)')" :description="__('Accounts created more recently than this are never suggested for downgrade. 0 disables the rule.')" type="number" min="0" max="365" :disabled="! $this->canManage()" />

            @if ($this->canManage())
                <flux:button variant="primary" type="submit" data-test="save-organization-button">{{ __('Save') }}</flux:button>
            @else
                <flux:callout icon="lock-closed" variant="secondary">{{ __('You have the viewer role. Ask an owner or admin to change these settings.') }}</flux:callout>
            @endif
        </form>
    </x-pages::settings.layout>
</section>
