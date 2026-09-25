<?php

use App\Actions\License\QueueLicenseActions;
use App\Models\LicenseAction;
use App\Models\ZoomMember;
use App\Tenancy\Tenancy;
use App\Zoom\Data\ZoomUser;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** Downgrade / restore for one member (free tier: one at a time). */
new class extends Component {
    #[Locked]
    public ZoomMember $member;

    public function mount(ZoomMember $member): void
    {
        $this->member = $member;
    }

    #[On('member-updated')]
    public function refresh(): void
    {
        $this->member = $this->member->fresh() ?? $this->member;
        unset($this->pending);
    }

    #[Computed]
    public function pending(): ?LicenseAction
    {
        return $this->member->actions()->where('status', LicenseAction::STATUS_QUEUED)->first();
    }

    #[Computed]
    public function lastAction(): ?LicenseAction
    {
        return $this->member->actions()->whereIn('status', [LicenseAction::STATUS_DONE, LicenseAction::STATUS_FAILED, LicenseAction::STATUS_SKIPPED])->first();
    }

    public function canManage(): bool
    {
        return auth()->user()->canManage(app(Tenancy::class)->currentOrFail());
    }

    public function downgrade(QueueLicenseActions $queue): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('update', $organization);

        if ($this->pending !== null) {
            return;
        }

        $queue->downgrade($organization, collect([$this->member]), LicenseAction::SOURCE_MANUAL, auth()->user());

        Flux::modal('confirm-downgrade-'.$this->member->id)->close();
        Flux::toast(text: __('Downgrade queued.'));
        $this->dispatch('member-updated');
    }

    public function restore(QueueLicenseActions $queue): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('update', $organization);

        if ($this->pending !== null) {
            return;
        }

        $queue->restore($organization, collect([$this->member]), LicenseAction::SOURCE_MANUAL, auth()->user());

        Flux::toast(text: __('Restore queued.'));
        $this->dispatch('member-updated');
    }

    public function poll(): void
    {
        $this->refresh();
        $this->dispatch('member-updated');
    }
}; ?>

<div @if ($this->pending) wire:poll.2s="poll" @endif>
    @if ($this->pending)
        <flux:callout icon="loading" variant="secondary">
            <flux:callout.text>{{ __(':action queued, waiting for the worker…', ['action' => ucfirst($this->pending->action)]) }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->canManage())
        @if ($member->isLicensed() && $member->eligible_for_downgrade && $member->isPresent())
            <flux:modal.trigger :name="'confirm-downgrade-'.$member->id">
                <flux:button variant="primary" icon="arrow-down-circle" data-test="downgrade-button">{{ __('Downgrade to Basic') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal :name="'confirm-downgrade-'.$member->id" class="md:w-[28rem]">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('Downgrade :name to Basic?', ['name' => $member->name ?: $member->email]) }}</flux:heading>
                        <flux:text class="mt-2">{{ __('SeatTrim re-checks the user in Zoom first (role, bundle, add-ons, upcoming meetings, exclusions) and skips if anything changed. You can restore with one click as long as a seat is free.') }}</flux:text>
                    </div>
                    <flux:callout icon="information-circle" variant="warning">
                        <flux:callout.text>{{ __('This does not reduce your Zoom bill until you lower the seat count in Zoom Billing.') }}</flux:callout.text>
                    </flux:callout>
                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                        <flux:button variant="primary" wire:click="downgrade" data-test="confirm-downgrade">{{ __('Downgrade') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        @elseif ($member->type === ZoomUser::TYPE_BASIC && $member->isPresent() && $member->actions()->where('action', 'downgrade')->where('status', 'done')->exists())
            <flux:button variant="filled" icon="arrow-up-circle" wire:click="restore" wire:confirm="{{ __('Restore the Licensed type? This needs a free seat in Zoom.') }}" data-test="restore-button">{{ __('Restore Licensed') }}</flux:button>
        @elseif ($member->isLicensed() && ! $member->eligible_for_downgrade)
            <flux:text class="text-sm">{{ __('No action available: a guardrail applies (see above).') }}</flux:text>
        @endif
    @endif

    @if ($this->lastAction && ! $this->pending)
        @php($a = $this->lastAction)
        <flux:callout class="mt-3" :icon="$a->status === 'done' ? 'check-circle' : 'exclamation-triangle'" :variant="$a->status === 'done' ? 'success' : ($a->status === 'failed' ? 'danger' : 'warning')">
            <flux:callout.text>
                {{ __('Last :action: :status', ['action' => $a->action, 'status' => $a->status]) }}@if ($a->reason) — {{ $a->reason }}@endif
                @if ($a->zoom_tracking_id)<span class="text-xs text-zinc-500"> · {{ __('Zoom tracking id') }} {{ $a->zoom_tracking_id }}</span>@endif
            </flux:callout.text>
        </flux:callout>
    @endif
</div>
