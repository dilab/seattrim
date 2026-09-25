<?php

use App\Enums\Bucket;
use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomConnection;
use App\Scan\ScanSettings;
use App\Support\Money;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $threshold = 90;

    public function mount(): void
    {
        $organization = $this->organization();
        $this->authorize('view', $organization);
        $this->threshold = ScanSettings::for($organization)->thresholdDays;

        $connection = ZoomConnection::query()->first();
        if ($connection === null || Scan::query()->where('status', Scan::STATUS_DONE)->doesntExist()) {
            $this->redirectRoute('onboarding', navigate: true);
        }
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    #[Computed]
    public function lastDone(): ?Scan
    {
        return Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->first();
    }

    #[Computed]
    public function latest(): ?Scan
    {
        return Scan::query()->latest('id')->first();
    }

    #[Computed]
    public function connection(): ?ZoomConnection
    {
        return ZoomConnection::query()->first();
    }

    public function scanNow(): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);

        if ($this->latest?->isRunning()) {
            return;
        }

        ScanOrganization::start($organization, Scan::TRIGGER_MANUAL);
        unset($this->latest);
        Flux::toast(text: __('Scan started.'));
    }

    public function updatedThreshold(int $value): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);
        $this->validate(['threshold' => ['required', Rule::in(ScanSettings::THRESHOLD_CHOICES)]]);

        $organization->setSetting('scan.threshold_days', $value);
        $organization->save();

        ScanOrganization::start($organization, Scan::TRIGGER_MANUAL);
        unset($this->latest);
        Flux::toast(text: __('Threshold saved. Re-scanning with :days days.', ['days' => $value]));
    }

    public function refreshScans(): void
    {
        unset($this->latest, $this->lastDone);
    }

    public function money(?int $cents): string
    {
        return Money::forOrganization($this->organization(), $cents);
    }
}; ?>

<div class="space-y-6" @if ($this->latest?->isRunning()) wire:poll.3s="refreshScans" @endif>
    @php($scan = $this->lastDone)
    @php($org = $this->organization())
    @php($monthly = $org->billing_cycle === 'monthly')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $org->name }}</flux:heading>
            <flux:text class="mt-1">
                @if ($scan)
                    {{ __('Last scan :when', ['when' => $scan->finished_at?->diffForHumans()]) }} ·
                    {{ __('idle threshold :days days', ['days' => $scan->threshold_days]) }}
                @else
                    {{ __('No completed scan yet.') }}
                @endif
            </flux:text>
        </div>
        <div class="flex items-center gap-3">
            @can('update', $org)
                <flux:select wire:model.live="threshold" size="sm" class="w-40">
                    @foreach (ScanSettings::THRESHOLD_CHOICES as $days)
                        <flux:select.option value="{{ $days }}">{{ __('Idle after :d days', ['d' => $days]) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button wire:click="scanNow" variant="primary" icon="arrow-path" size="sm" :disabled="$this->latest?->isRunning()" data-test="scan-now">
                    {{ $this->latest?->isRunning() ? __('Scanning…') : __('Scan now') }}
                </flux:button>
            @endcan
        </div>
    </div>

    @if ($this->latest?->status === 'failed')
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('The latest scan failed') }}</flux:callout.heading>
            <flux:callout.text>{{ $this->latest->error }} {{ __('Showing the last successful scan.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->connection && ! $this->connection->isActive())
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.text>{{ __('Zoom access is no longer valid. Scans and automation are paused until you reconnect.') }}</flux:callout.text>
            <flux:callout.link :href="route('connection.edit')" wire:navigate>{{ __('Reconnect') }}</flux:callout.link>
        </flux:callout>
    @endif

    @if ($scan)
        {{-- Headline --}}
        <flux:card class="space-y-3">
            <flux:text>{{ __('Licensed seats you are paying for but not using') }}</flux:text>
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <span class="text-4xl font-semibold tracking-tight" data-test="headline-waste">{{ $this->money($scan->total('waste_annual_cents')) }}<span class="text-lg font-normal text-zinc-500">/{{ __('yr') }}</span></span>
                @if ($monthly)
                    <span class="text-zinc-500">{{ $this->money($scan->total('waste_monthly_cents')) }}/{{ __('mo') }}</span>
                @endif
                <span class="text-zinc-500">· {{ trans_choice(':n seat|:n seats', (int) $scan->total('reclaimable_seats'), ['n' => $scan->total('reclaimable_seats')]) }} {{ __('reclaimable') }}</span>
            </div>
            <flux:callout icon="information-circle" variant="secondary">
                <flux:callout.text>
                    {{ __('Downgrading a user does not reduce your Zoom bill by itself. Seats stay billable until you lower the license quantity on Zoom\'s Billing page, and annual plans do not refund mid-term. Freeing seats stops new hires from triggering new purchases, and tells you what to cut at renewal.') }}
                </flux:callout.text>
            </flux:callout>
        </flux:card>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Seats --}}
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Seats') }}</flux:heading>
                @if ($scan->total('unassigned.known'))
                    <dl class="grid grid-cols-3 gap-2 text-center">
                        <div><dt class="text-xs text-zinc-500">{{ __('Purchased') }}</dt><dd class="text-2xl font-semibold">{{ $scan->total('purchased') }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('Assigned') }}</dt><dd class="text-2xl font-semibold">{{ $scan->total('used') }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('Unassigned') }}</dt><dd class="text-2xl font-semibold text-amber-600">{{ $scan->total('unassigned.count') }}</dd></div>
                    </dl>
                    <flux:text class="text-xs">{{ __('Unassigned seats cost :money per year and nobody holds them. Reduce the quantity in Zoom Billing.', ['money' => $this->money($scan->total('unassigned.annual_cents'))]) }}</flux:text>
                @else
                    <dl class="grid grid-cols-2 gap-2 text-center">
                        <div><dt class="text-xs text-zinc-500">{{ __('Licensed users') }}</dt><dd class="text-2xl font-semibold">{{ $scan->total('licensed_total') }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('Purchased') }}</dt><dd class="text-2xl font-semibold text-zinc-400">?</dd></div>
                    </dl>
                    <flux:text class="text-xs">{{ __('Zoom did not expose plan usage for this account, so purchased and unassigned seats are unknown. Check Zoom Billing for the purchased quantity.') }}</flux:text>
                @endif
            </flux:card>

            {{-- Renewal --}}
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Renewal') }}</flux:heading>
                @php($purchased = $scan->total('purchased'))
                @php($reclaimable = (int) $scan->total('reclaimable_seats'))
                @if ($org->renewal_date && $purchased !== null)
                    <flux:text>
                        {{ __('You pay for :p seats, :u are assigned, :r are reclaimable. At renewal on :date, reduce to :n seats → :money per year.', [
                            'p' => $purchased, 'u' => $scan->total('used'), 'r' => $reclaimable, 'date' => $org->renewal_date->toFormattedDateString(),
                            'n' => max(0, $purchased - $reclaimable), 'money' => $this->money(max(0, $purchased - $reclaimable) * $org->annualSeatPriceCents()),
                        ]) }}
                    </flux:text>
                    <flux:text class="text-xs">{{ trans_choice(':n day to go|:n days to go', (int) now()->diffInDays($org->renewal_date, false), ['n' => (int) now()->diffInDays($org->renewal_date, false)]) }}</flux:text>
                @elseif ($org->renewal_date)
                    <flux:text>{{ __('Renews on :date. :r seats are reclaimable; check the purchased quantity in Zoom Billing before renewal.', ['date' => $org->renewal_date->toFormattedDateString(), 'r' => $reclaimable]) }}</flux:text>
                @else
                    <flux:text>{{ __('Add your Zoom renewal date to get a right-sizing target and reminders 60, 30 and 7 days before renewal.') }}</flux:text>
                    <flux:button size="sm" variant="filled" :href="route('organization.edit')" wire:navigate>{{ __('Add renewal date') }}</flux:button>
                @endif
                <flux:link href="https://zoom.us/billing" target="_blank" class="text-xs">{{ __('Open Zoom Billing') }} ↗</flux:link>
            </flux:card>
        </div>

        {{-- Buckets --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([Bucket::DeactivatedLicensed, Bucket::PendingLicensed, Bucket::IdleLicensed, Bucket::Protected, Bucket::Healthy] as $bucket)
                @php($b = $scan->total('buckets.'.$bucket->value))
                <a href="{{ route('members', ['bucket' => $bucket->value]) }}" wire:navigate class="block rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500" data-test="bucket-{{ $bucket->value }}">
                    <div class="flex items-center justify-between">
                        <flux:badge size="sm" :color="$bucket->color()">{{ $bucket->label() }}</flux:badge>
                    </div>
                    <div class="mt-3 text-3xl font-semibold">{{ $b['count'] ?? 0 }}</div>
                    <div class="text-sm text-zinc-500">
                        @if ($bucket->isWaste())
                            {{ $this->money($b['annual_cents'] ?? 0) }}/{{ __('yr') }}
                        @else
                            {{ $bucket === Bucket::Protected ? __('guardrails apply') : __('no action') }}
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Data quality --}}
        @if (! empty($scan->warnings))
            <flux:callout icon="exclamation-triangle" variant="warning">
                <flux:callout.heading>{{ __('Data-quality warnings from this scan') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="list-disc space-y-1 ps-5">
                        @foreach ($scan->warnings as $warning)
                            <li>{{ $warning['message'] }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif
    @endif
</div>
