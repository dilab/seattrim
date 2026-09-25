<?php

use App\Actions\Zoom\DisconnectZoom;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Zoom connection')] class extends Component {
    public string $confirmation = '';

    public function mount(): void
    {
        $this->authorize('view', app(Tenancy::class)->currentOrFail());
    }

    #[Computed]
    public function connection(): ?ZoomConnection
    {
        return ZoomConnection::query()->first();
    }

    public function isDemo(): bool
    {
        return config('zoom.driver') === 'fake';
    }

    public function disconnect(DisconnectZoom $disconnect): void
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $this->authorize('delete', $organization);

        if ($this->confirmation !== 'DELETE') {
            $this->addError('confirmation', __('Type DELETE to confirm.'));

            return;
        }

        $connection = $this->connection;

        if ($connection !== null) {
            $disconnect->handle($connection, DisconnectZoom::REASON_MANUAL);
        }

        unset($this->connection);
        $this->reset('confirmation');
        Flux::modal('disconnect-zoom')->close();
        Flux::toast(variant: 'success', text: __('Zoom disconnected and all Zoom data deleted.'));
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Zoom connection') }}</flux:heading>
        <flux:text class="mt-1">{{ __('SeatTrim connects to one Zoom account per organization through an admin-managed Marketplace app.') }}</flux:text>
    </div>

    @if (session('zoom.connected'))
        <flux:callout icon="check-circle" variant="success">{{ __('Zoom connected. The first scan starts on the dashboard.') }}</flux:callout>
    @endif
    @if (session('zoom.error'))
        <flux:callout icon="exclamation-triangle" variant="danger">{{ session('zoom.error') }}</flux:callout>
    @endif
    @if ($this->isDemo())
        <flux:callout icon="beaker" variant="warning">{{ __('Demo mode: this environment uses a built-in fake Zoom account with 69 sample users. Nothing you do here touches a real Zoom account.') }}</flux:callout>
    @endif

    @php($connection = $this->connection)

    <flux:card class="space-y-4">
        @if ($connection === null)
            <flux:heading size="lg">{{ __('Not connected') }}</flux:heading>
            @if (app(Tenancy::class)->currentOrFail()->setting('zoom.disconnected_at'))
                <flux:text>{{ __('Zoom was :reason on :date. All Zoom-derived data was deleted at that time.', ['reason' => DisconnectZoom::describeReason((string) app(Tenancy::class)->currentOrFail()->setting('zoom.disconnect_reason')), 'date' => Illuminate\Support\Carbon::parse(app(Tenancy::class)->currentOrFail()->setting('zoom.disconnected_at'))->toDayDateTimeString()]) }}</flux:text>
            @else
                <flux:text>{{ __('Connect as a Zoom account owner or admin. SeatTrim asks for read access to users, reports and plan usage, plus the single permission to change a user between Licensed and Basic.') }}</flux:text>
            @endif
            @can('update', app(Tenancy::class)->currentOrFail())
                <form method="POST" action="{{ route('zoom.connect') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" icon="link" data-test="connect-zoom-button">{{ __('Connect Zoom') }}</flux:button>
                </form>
            @endcan
        @else
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Connected') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Zoom account :id, installed by :email on :date.', ['id' => $connection->zoom_account_id, 'email' => $connection->installer_email ?? '—', 'date' => $connection->connected_at?->toDayDateTimeString() ?? '—']) }}</flux:text>
                </div>
                <flux:badge :color="$connection->isActive() ? 'green' : 'red'" size="lg">{{ ucfirst($connection->status) }}</flux:badge>
            </div>

            @if (! $connection->isActive())
                <flux:callout icon="exclamation-triangle" variant="danger">
                    {{ __('Zoom access stopped working: :error. Reconnect to resume scans.', ['error' => $connection->last_error ?? __('unknown error')]) }}
                </flux:callout>
            @endif

            <div>
                <flux:heading size="sm">{{ __('Scopes granted') }}</flux:heading>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($connection->scopes ?? [] as $scope)
                        <flux:badge size="sm" color="zinc">{{ $scope }}</flux:badge>
                    @endforeach
                </div>
                @if ($connection->missingScopes() !== [])
                    <flux:callout icon="exclamation-triangle" variant="warning" class="mt-3">
                        {{ __('Missing scopes: :scopes. Some parts of the scan will be skipped. Reconnect to grant them.', ['scopes' => implode(', ', $connection->missingScopes())]) }}
                    </flux:callout>
                @endif
            </div>

            <flux:text class="text-xs">{{ __('Token refreshed :when. Tokens are stored encrypted and never logged.', ['when' => $connection->last_refreshed_at?->diffForHumans() ?? '—']) }}</flux:text>

            <div class="flex flex-wrap gap-3">
                @can('update', app(Tenancy::class)->currentOrFail())
                    <form method="POST" action="{{ route('zoom.connect') }}">
                        @csrf
                        <flux:button type="submit" variant="filled" icon="arrow-path">{{ __('Reconnect') }}</flux:button>
                    </form>
                @endcan
                @can('delete', app(Tenancy::class)->currentOrFail())
                    <flux:modal.trigger name="disconnect-zoom">
                        <flux:button variant="danger" icon="trash" data-test="disconnect-zoom-button">{{ __('Disconnect and delete data') }}</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
        @endif
    </flux:card>

    <flux:card class="space-y-2">
        <flux:heading size="sm">{{ __('What SeatTrim stores') }}</flux:heading>
        <flux:text>{{ __('User id, email, name, department, license type, status, role, creation date, last login, hosting counts per window, add-on flags, and the count of upcoming meetings. No meeting content, recordings, chat or participant lists.') }}</flux:text>
        <flux:text>{{ __('Disconnecting here, or removing the app in the Zoom Marketplace, deletes all of it immediately.') }}</flux:text>
    </flux:card>

    <flux:modal name="disconnect-zoom" class="md:w-96">
        <form wire:submit="disconnect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Disconnect Zoom?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This revokes SeatTrim\'s access and permanently deletes the user inventory, scans and per-user audit log for this organization. Nothing changes in Zoom itself: users keep whatever license type they have now.') }}</flux:text>
            </div>
            <flux:input wire:model="confirmation" :label="__('Type DELETE to confirm')" autocomplete="off" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="confirm-disconnect-button">{{ __('Disconnect and delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
