<?php

use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomConnection;
use App\Support\Money;
use App\Tenancy\Tenancy;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Onboarding wizard: connect Zoom → (optional) billing details → first scan with progress → dashboard.
 * The step is derived from state, so refreshing or returning later lands on the right step.
 */
new #[Title('Get started')] class extends Component {
    public string $seat_price = '149.00';
    public string $billing_cycle = 'annual';
    public ?string $renewal_date = null;
    public string $currency = 'USD';

    public function mount(): void
    {
        $organization = $this->organization();
        $this->authorize('view', $organization);

        $this->seat_price = number_format($organization->seat_price_cents / 100, 2, '.', '');
        $this->billing_cycle = $organization->billing_cycle ?? 'annual';
        $this->renewal_date = $organization->renewal_date?->toDateString();
        $this->currency = (string) $organization->setting('currency', 'USD');

        if ($this->step() === 'done') {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    #[Computed]
    public function connection(): ?ZoomConnection
    {
        return ZoomConnection::query()->first();
    }

    #[Computed]
    public function scan(): ?Scan
    {
        return Scan::query()->latest('id')->first();
    }

    /** connect | billing | scanning | done */
    public function step(): string
    {
        $connection = $this->connection;
        if ($connection === null || ! $connection->isActive()) {
            return 'connect';
        }

        $scan = $this->scan;
        if ($scan === null) {
            return 'billing';
        }

        return $scan->isFinished() ? 'done' : 'scanning';
    }

    public function saveAndScan(bool $skip = false): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);

        if (! $skip) {
            $validated = $this->validate([
                'seat_price' => ['required', 'numeric', 'min:0', 'max:100000'],
                'billing_cycle' => ['required', Rule::in(Organization::BILLING_CYCLES)],
                'renewal_date' => ['nullable', 'date'],
                'currency' => ['required', Rule::in(Money::CURRENCIES)],
            ]);

            $organization->fill([
                'seat_price_cents' => (int) round(((float) $validated['seat_price']) * 100),
                'billing_cycle' => $validated['billing_cycle'],
                'renewal_date' => $validated['renewal_date'] ?: null,
            ]);
            $organization->setSetting('currency', $validated['currency']);
            $organization->save();
        } else {
            $organization->setSetting('onboarding.billing_skipped_at', now()->toIso8601String());
            $organization->save();
        }

        ScanOrganization::start($organization, Scan::TRIGGER_ONBOARDING);
        unset($this->scan);
    }

    /** Called by wire:poll while scanning. */
    public function checkScan(): void
    {
        unset($this->scan);

        if ($this->step() === 'done') {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function retryScan(): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);
        ScanOrganization::start($organization, Scan::TRIGGER_ONBOARDING);
        unset($this->scan);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-8" @if ($this->step() === 'scanning') wire:poll.2s="checkScan" @endif>
    @php($step = $this->step())
    @php($steps = ['connect' => 'Connect Zoom', 'billing' => 'Your Zoom bill', 'scanning' => 'First scan'])

    <div>
        <flux:heading size="xl">{{ __('Set up :org', ['org' => $this->organization()->name]) }}</flux:heading>
        <ol class="mt-4 flex flex-wrap gap-2 text-sm">
            @foreach ($steps as $key => $label)
                @php($state = array_search($key, array_keys($steps)) < array_search($step, array_keys($steps)) ? 'done' : ($key === $step ? 'current' : 'todo'))
                <li class="flex items-center gap-2 rounded-full border px-3 py-1 {{ $state === 'current' ? 'border-blue-500 text-blue-700 dark:text-blue-300' : ($state === 'done' ? 'border-green-500 text-green-700 dark:text-green-300' : 'border-zinc-300 text-zinc-500 dark:border-zinc-600') }}">
                    <span class="text-xs font-semibold">{{ $loop->iteration }}</span> {{ __($label) }}
                </li>
            @endforeach
        </ol>
    </div>

    @if ($step === 'connect')
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Connect your Zoom account') }}</flux:heading>
            <flux:text>{{ __('You need to be a Zoom account owner or admin. SeatTrim reads users, host reports and plan usage, and can change a user between Licensed and Basic when you ask it to. It never reads meeting content.') }}</flux:text>
            @if (session('zoom.error'))
                <flux:callout icon="exclamation-triangle" variant="danger">{{ session('zoom.error') }}</flux:callout>
            @endif
            @if (config('zoom.driver') === 'fake')
                <flux:callout icon="beaker" variant="warning">{{ __('Demo mode: a built-in sample Zoom account with 69 users will be connected. No real Zoom account is involved.') }}</flux:callout>
            @endif
            @can('update', $this->organization())
                <form method="POST" action="{{ route('zoom.connect') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" icon="link" data-test="onboarding-connect">{{ __('Connect Zoom') }}</flux:button>
                </form>
            @else
                <flux:callout icon="lock-closed" variant="secondary">{{ __('Ask an owner or admin of this organization to connect Zoom.') }}</flux:callout>
            @endcan
        </flux:card>
    @elseif ($step === 'billing')
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('What do you pay Zoom per seat?') }}</flux:heading>
            <flux:text>{{ __('Used to show waste in money and to remind you before renewal. You can change it later in Settings. Skip if you do not have the invoice handy.') }}</flux:text>
            <form wire:submit="saveAndScan(false)" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="billing_cycle" :label="__('Billing cycle')">
                        <flux:select.option value="annual">{{ __('Annual') }}</flux:select.option>
                        <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="currency" :label="__('Currency')">
                        @foreach (Money::CURRENCIES as $code)
                            <flux:select.option value="{{ $code }}">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="seat_price" :label="__('Price per licensed seat per billing period')" type="number" step="0.01" min="0" :description="__('Zoom Pro list price is about 149 per year or 15.99 per month per seat.')" />
                <flux:input wire:model="renewal_date" :label="__('Zoom renewal date (optional)')" type="date" :description="__('Zoom web portal > Account Management > Billing > Current Plans.')" />
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" variant="primary" icon="play" data-test="onboarding-save-scan">{{ __('Save and run first scan') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="saveAndScan(true)" data-test="onboarding-skip">{{ __('Skip for now') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @elseif ($step === 'scanning')
        @php($scan = $this->scan)
        <flux:card class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:icon.loading class="text-blue-500" />
                <flux:heading size="lg">{{ $scan?->status === 'queued' ? __('Waiting for a worker to pick up the scan…') : __('Scanning your Zoom account…') }}</flux:heading>
            </div>
            <flux:text>{{ __('Reading users in every status, the last :days days of host reports and your plan usage. This usually takes under a minute; large accounts can take a few.', ['days' => $scan?->threshold_days ?: 90]) }}</flux:text>
            <flux:text class="text-xs">{{ __('Started :when.', ['when' => $scan?->created_at?->diffForHumans() ?? '—']) }}</flux:text>
            @if ($scan?->status === 'queued' && config('queue.default') !== 'sync')
                <flux:callout icon="information-circle" variant="secondary">{{ __('If this never moves, no queue worker is running. Locally: composer dev.') }}</flux:callout>
            @endif
        </flux:card>
    @endif

    @if ($step !== 'scanning' && $this->scan?->status === 'failed')
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('The last scan failed') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->scan->error }}</flux:callout.text>
            @can('update', $this->organization())
                <flux:button size="sm" class="mt-2" wire:click="retryScan">{{ __('Try again') }}</flux:button>
            @endcan
        </flux:callout>
    @endif
</div>
