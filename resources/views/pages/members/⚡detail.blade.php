<?php

use App\Enums\Bucket;
use App\Models\ZoomMember;
use App\Scan\Window;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The member drawer: why a member is in its bucket, what protects it, and its action history.
 * Actions (downgrade/restore) are added in M5 through the actions child component.
 */
new class extends Component {
    #[Locked]
    public ZoomMember $member;

    public function mount(ZoomMember $member): void
    {
        $this->authorize('view', $member->organization);
        $this->member = $member;
    }

    #[On('member-updated')]
    public function refreshMember(): void
    {
        $this->member = $this->member->fresh(['actions.performer']) ?? $this->member;
    }
}; ?>

<div class="space-y-6" wire:key="member-detail-{{ $member->id }}">
    <div>
        <flux:heading size="lg">{{ $member->name ?: $member->email }}</flux:heading>
        <flux:text>{{ $member->email }}@if ($member->dept) · {{ $member->dept }}@endif</flux:text>
        <div class="mt-3 flex flex-wrap gap-2">
            <flux:badge size="sm" :color="$member->bucket->color()">{{ $member->bucket->label() }}</flux:badge>
            <flux:badge size="sm" color="zinc">{{ $member->typeLabel() }}</flux:badge>
            <flux:badge size="sm" color="zinc">{{ $member->statusLabel() }}</flux:badge>
            @if ($member->role_name || in_array($member->role_id, ['0', '1'], true))
                <flux:badge size="sm" color="purple">{{ $member->role_name ?: ($member->role_id === '0' ? 'Owner' : 'Admin') }}</flux:badge>
            @endif
            @if ($member->is_room)<flux:badge size="sm" color="cyan">{{ __('Zoom Room') }}</flux:badge>@endif
        </div>
    </div>

    <flux:card class="space-y-2">
        <flux:heading size="sm">{{ __('Why this bucket') }}</flux:heading>
        <flux:text>{{ $member->bucket->description() }}</flux:text>
        @if (! empty($member->protected_reasons))
            <ul class="list-disc space-y-1 ps-5 text-sm">
                @foreach ($member->protected_reasons as $reason)
                    <li>{{ ucfirst($reason) }}</li>
                @endforeach
            </ul>
        @endif
        @if ($member->bucket === Bucket::DeactivatedLicensed)
            <flux:callout icon="information-circle" variant="secondary" class="mt-2">
                <flux:callout.text>{{ __('Zoom documents that deactivating a user removes their licenses, so this user still holding one is an anomaly worth fixing.') }} {{ __('Zoom does not document whether a deactivated user\'s type can be changed by API. SeatTrim tries it; if Zoom refuses, reactivate the user in Zoom, downgrade, then deactivate again. Deleting the user with recording transfer only works for active users.') }}</flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <livewire:pages::members.actions :member="$member" :wire:key="'actions-'.$member->id" />

    <flux:card class="space-y-3">
        <flux:heading size="sm">{{ __('Hosting activity') }}</flux:heading>
        @if ($member->last_hosted_window === Window::UNKNOWN)
            <flux:text>{{ __('Unknown: the host report could not be read in the last scan.') }}</flux:text>
        @else
            <div class="grid grid-cols-3 gap-2 text-center sm:grid-cols-6">
                @foreach ($member->meetings_by_window ?? [] as $label => $count)
                    <div class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">{{ $label }}d</div>
                        <div class="text-lg font-semibold {{ $count > 0 ? '' : 'text-zinc-400' }}">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
            <flux:text class="text-xs">{{ __('Meetings hosted per window of days ago. Joining someone else\'s meeting does not count as hosting.') }}</flux:text>
        @endif
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-zinc-500">{{ __('Last hosted') }}</dt>
            <dd>{{ $member->last_hosted_window === 'none' ? __('never in the scanned period') : ($member->last_hosted_window === 'unknown' ? __('unknown') : __(':w days ago', ['w' => $member->last_hosted_window])) }}</dd>
            <dt class="text-zinc-500 flex items-center gap-1">{{ __('Last login') }}
                <flux:tooltip :content="__('Zoom only updates last login if the previous login was more than 3 days earlier, and leaves it empty for deactivated users. Logging in is not hosting.')" toggleable>
                    <flux:button icon="information-circle" size="xs" variant="ghost" />
                </flux:tooltip>
            </dt>
            <dd>{{ $member->last_login_at?->toFormattedDateString() ?? '—' }}</dd>
            <dt class="text-zinc-500">{{ __('Upcoming meetings') }}</dt>
            <dd>{{ $member->upcoming_meetings_count ?? __('not checked') }}</dd>
            <dt class="text-zinc-500">{{ __('Created in Zoom') }}</dt>
            <dd>{{ $member->created_at_zoom?->toFormattedDateString() ?? '—' }}</dd>
            <dt class="text-zinc-500">{{ __('Add-ons') }}</dt>
            <dd>{{ empty($member->add_ons) ? __('none detected') : implode(', ', $member->add_ons) }}</dd>
            <dt class="text-zinc-500">{{ __('Bundle') }}</dt>
            <dd>{{ $member->has_bundled_license ? __('Workplace / United bundle') : ($member->bundle_known ? __('none') : __('unknown')) }}</dd>
            @if ($member->isKeptByUser())
                <dt class="text-zinc-500">{{ __('Kept by user until') }}</dt>
                <dd>{{ $member->excluded_until?->toFormattedDateString() }}</dd>
            @endif
        </dl>
    </flux:card>

    <flux:card class="space-y-2">
        <flux:heading size="sm">{{ __('Action history') }}</flux:heading>
        @forelse ($member->actions as $action)
            <div class="flex items-start justify-between gap-2 border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-800">
                <div>
                    <span class="font-medium">{{ ucfirst($action->action) }}</span>
                    <span class="text-zinc-500">· {{ $action->source }}@if ($action->dry_run) · {{ __('dry run') }}@endif · {{ $action->performer?->name ?? __('rule') }}</span>
                    @if ($action->reason)<div class="text-xs text-zinc-500">{{ $action->reason }}</div>@endif
                </div>
                <div class="text-end">
                    <flux:badge size="sm" :color="match ($action->status) { 'done' => 'green', 'failed' => 'red', 'skipped' => 'amber', default => 'zinc' }">{{ $action->status }}</flux:badge>
                    <div class="text-xs text-zinc-500">{{ $action->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <flux:text class="text-sm">{{ __('No actions yet.') }}</flux:text>
        @endforelse
    </flux:card>
</div>
