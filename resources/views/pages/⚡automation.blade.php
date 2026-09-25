<?php

use App\Automation\AutomationSettings;
use App\Enums\Bucket;
use App\Models\DowngradeNotice;
use App\Models\Organization;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Automation')] class extends Component {
    use WithPagination;

    public bool $enabled = false;
    public bool $dry_run = true;
    public int $warning_days = 7;
    /** @var array<int, string> */
    public array $buckets = [];
    public bool $weekly_digest = true;
    public string $reply_to = '';

    public function mount(): void
    {
        $organization = $this->organization();
        $this->authorize('view', $organization);

        $settings = AutomationSettings::for($organization);
        $this->enabled = $settings->enabled;
        $this->dry_run = $settings->dryRun;
        $this->warning_days = $settings->warningDays;
        $this->buckets = $settings->buckets;
        $this->weekly_digest = $settings->weeklyDigest;
        $this->reply_to = $settings->replyTo ?? '';
    }

    public function organization(): Organization
    {
        return app(Tenancy::class)->currentOrFail();
    }

    public function allowed(): bool
    {
        return Features::allows($this->organization(), Features::AUTOMATION);
    }

    public function canManage(): bool
    {
        return auth()->user()->canManage($this->organization());
    }

    public function save(): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);

        if (! $this->allowed()) {
            Flux::toast(variant: 'danger', text: Features::deniedMessage(Features::AUTOMATION));

            return;
        }

        $validated = $this->validate([
            'enabled' => ['boolean'],
            'dry_run' => ['boolean'],
            'warning_days' => ['required', 'integer', 'min:1', 'max:60'],
            'buckets' => ['array'],
            'buckets.*' => [Rule::in(AutomationSettings::allowedBuckets())],
            'weekly_digest' => ['boolean'],
            'reply_to' => ['nullable', 'email'],
        ]);

        $organization->setSetting('automation', (new AutomationSettings(
            enabled: (bool) $validated['enabled'],
            dryRun: (bool) $validated['dry_run'],
            warningDays: (int) $validated['warning_days'],
            buckets: array_values($validated['buckets'] ?? []),
            weeklyDigest: (bool) $validated['weekly_digest'],
            replyTo: $validated['reply_to'] ?: null,
        ))->toArray());
        $organization->save();

        Flux::toast(variant: 'success', text: __('Automation settings saved.'));
    }

    /** @return LengthAwarePaginator<int, DowngradeNotice> */
    #[Computed]
    public function notices(): LengthAwarePaginator
    {
        return DowngradeNotice::query()->with(['member', 'executedAction'])->latest('id')->paginate(25);
    }

    public function cancel(int $noticeId): void
    {
        $this->authorize('update', $this->organization());
        $notice = DowngradeNotice::query()->findOrFail($noticeId);

        if ($notice->isOpen()) {
            $notice->forceFill(['cancelled_at' => now(), 'cancel_reason' => 'cancelled by '.auth()->user()->name])->save();
            unset($this->notices);
            Flux::toast(text: __('Warning cancelled.'));
        }
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Automation') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Warn idle users by email, then downgrade them automatically after a grace period, with the same guardrails as manual actions. Off by default; dry run first.') }}</flux:text>
    </div>

    @if (! $this->allowed())
        <flux:callout icon="lock-closed" variant="warning">
            <flux:callout.text>{{ Features::deniedMessage(Features::AUTOMATION) }}</flux:callout.text>
            <flux:callout.link :href="route('billing')" wire:navigate>{{ __('See plans') }}</flux:callout.link>
        </flux:callout>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-5">
            <flux:switch wire:model="enabled" :label="__('Automation enabled')" :description="__('Runs after each nightly scan.')" :disabled="! $this->canManage() || ! $this->allowed()" />
            <flux:switch wire:model="dry_run" :label="__('Dry run')" :description="__('Send warnings and log what would happen, but do not change anyone in Zoom. Recommended for the first two weeks.')" :disabled="! $this->canManage() || ! $this->allowed()" />

            <flux:input wire:model="warning_days" type="number" min="1" max="60" :label="__('Warn users this many days before the downgrade')" :disabled="! $this->canManage() || ! $this->allowed()" />

            <flux:checkbox.group wire:model="buckets" :label="__('Buckets included')" :description="__('Deactivated users are never automated: handle them from the members page.')">
                @foreach (AutomationSettings::allowedBuckets() as $bucket)
                    <flux:checkbox :value="$bucket" :label="Bucket::from($bucket)->label()" :disabled="! $this->canManage() || ! $this->allowed()" />
                @endforeach
            </flux:checkbox.group>

            <flux:input wire:model="reply_to" type="email" :label="__('Reply-to address for warning emails (optional)')" :description="__('Users who reply to the warning reach this address, for example your IT helpdesk.')" :disabled="! $this->canManage() || ! $this->allowed()" />

            <flux:switch wire:model="weekly_digest" :label="__('Weekly digest to owners and admins')" :description="__('Monday 08:00 in your timezone: what was downgraded, kept, pending, current waste and the renewal reminder.')" :disabled="! $this->canManage() || ! $this->allowed()" />

            <flux:text class="text-xs">{{ __('Idle threshold (:days days) and new-account protection are set in Organization settings. Each run downgrades at most :cap users; the rest wait for the next night.', ['days' => App\Scan\ScanSettings::for($this->organization())->thresholdDays, 'cap' => App\Actions\License\SafetyCap::limit()]) }}</flux:text>

            @if ($this->canManage() && $this->allowed())
                <flux:button type="submit" variant="primary" data-test="save-automation">{{ __('Save') }}</flux:button>
            @endif
        </form>
    </flux:card>

    <div>
        <flux:heading size="lg">{{ __('Warnings sent') }}</flux:heading>
        <flux:table :paginate="$this->notices" class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Warned') }}</flux:table.column>
                <flux:table.column>{{ __('Downgrade on') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->notices as $notice)
                    <flux:table.row :key="$notice->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $notice->member?->name ?: '—' }}</div>
                            <flux:text class="text-xs">{{ $notice->member?->email }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $notice->sent_at?->toFormattedDateString() ?? __('not emailed (pending invite)') }}</flux:table.cell>
                        <flux:table.cell>{{ $notice->scheduled_for->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($notice->statusLabel()) { 'executed' => 'green', 'kept' => 'blue', 'cancelled' => 'zinc', default => 'amber' }">{{ $notice->statusLabel() }}</flux:badge>
                            @if ($notice->cancel_reason)<div class="text-xs text-zinc-500">{{ $notice->cancel_reason }}</div>@endif
                            @if ($notice->executedAction)<div class="text-xs text-zinc-500">{{ $notice->executedAction->status }}@if ($notice->executedAction->reason): {{ $notice->executedAction->reason }}@endif</div>@endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            @if ($notice->isOpen() && $this->canManage())
                                <flux:button size="xs" variant="ghost" wire:click="cancel({{ $notice->id }})">{{ __('Cancel') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No warnings sent yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
