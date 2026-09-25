<?php

namespace App\Jobs;

use App\Actions\Zoom\DisconnectZoom;
use App\Models\ZoomConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Routes a verified Zoom webhook to the right handler. Idempotent: Zoom retries
 * on 5xx and may deliver an event more than once.
 */
class HandleZoomWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $event,
        public array $payload,
        public int $eventTs,
        public string $trackingId = '',
    ) {}

    public function handle(DisconnectZoom $disconnect): void
    {
        Log::info('zoom.webhook.received', ['event' => $this->event, 'tracking_id' => $this->trackingId, 'account_id' => $this->payload['account_id'] ?? null]);

        match (true) {
            $this->event === 'app_deauthorized' => $this->deauthorize($disconnect),
            str_starts_with($this->event, 'user.') => $this->userEvent(),
            default => Log::info('zoom.webhook.ignored', ['event' => $this->event]),
        };
    }

    private function deauthorize(DisconnectZoom $disconnect): void
    {
        $accountId = (string) ($this->payload['account_id'] ?? '');

        if ($accountId === '') {
            return;
        }

        $connections = ZoomConnection::query()->allOrganizations()->where('zoom_account_id', $accountId)->get();

        foreach ($connections as $connection) {
            $disconnect->handle($connection, DisconnectZoom::REASON_DEAUTHORIZED, revokeRemotely: false);
        }
    }

    private function userEvent(): void
    {
        $accountId = (string) ($this->payload['account_id'] ?? '');
        $object = (array) ($this->payload['object'] ?? []);

        if ($accountId === '' || $object === []) {
            return;
        }

        $connection = ZoomConnection::query()->allOrganizations()->where('zoom_account_id', $accountId)->where('status', ZoomConnection::STATUS_ACTIVE)->first();

        if ($connection === null) {
            return;
        }

        SyncZoomMemberFromWebhook::dispatch($connection->organization_id, $this->event, $object);
    }
}
