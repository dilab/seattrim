<?php

namespace App\Actions\Zoom;

use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Notifications\ZoomDisconnected;
use App\Zoom\Contracts\ZoomApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Revoke, purge, notify. Used by the deauthorization webhook and by the
 * Disconnect button. Idempotent: a second call for the same account is a no-op.
 */
class DisconnectZoom
{
    public const REASON_DEAUTHORIZED = 'deauthorized';

    public const REASON_MANUAL = 'manual';

    public function __construct(private readonly ZoomApi $zoom, private readonly PurgeZoomData $purge) {}

    public function handle(ZoomConnection $connection, string $reason, bool $revokeRemotely = true): void
    {
        $organization = $connection->organization;

        if ($revokeRemotely && $connection->access_token) {
            try {
                $this->zoom->revokeToken((string) $connection->access_token);
            } catch (Throwable $e) {
                Log::notice('zoom.disconnect.revoke_failed', ['connection' => $connection->describe(), 'error' => $e->getMessage()]);
            }
        }

        $connection->markRevoked($reason);

        $deleted = $this->purge->handle($organization);

        $organization->refresh();
        $organization->setSetting('zoom.disconnected_at', now()->toIso8601String());
        $organization->setSetting('zoom.disconnect_reason', $reason);
        $organization->setSetting('zoom.last_account_id', $connection->zoom_account_id);
        $organization->save();

        Log::info('zoom.disconnected', ['organization' => $organization->id, 'reason' => $reason, 'deleted' => $deleted]);

        Notification::send($organization->owners(), new ZoomDisconnected($organization, $reason));
    }

    public static function describeReason(string $reason): string
    {
        return match ($reason) {
            self::REASON_DEAUTHORIZED => 'removed from the Zoom App Marketplace by a Zoom admin',
            self::REASON_MANUAL => 'disconnected from SeatTrim by an organization owner',
            default => $reason,
        };
    }

    public static function forOrganization(Organization $organization): ?ZoomConnection
    {
        return ZoomConnection::query()->allOrganizations()->where('organization_id', $organization->getKey())->first();
    }
}
