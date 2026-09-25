<?php

namespace App\Zoom;

use App\Models\ZoomConnection;
use App\Notifications\ZoomConnectionRevoked;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Hands out a valid access token for a connection, refreshing under a lock so
 * that concurrent jobs never burn the (rotating) refresh token twice.
 * docs/zoom-api-notes.md §2.
 */
class TokenManager
{
    public function accessToken(ZoomConnection $connection): string
    {
        if (! $connection->isActive()) {
            throw new ConnectionRevokedException("Zoom connection {$connection->id} is {$connection->status}; the organization must reconnect.");
        }

        if ($this->isFresh($connection)) {
            return (string) $connection->access_token;
        }

        return $this->refresh($connection);
    }

    /**
     * Refresh now. With $force the current token is discarded even if it looks fresh
     * (used after Zoom answered 401 / code 124 to a request).
     */
    public function refresh(ZoomConnection $connection, bool $force = false): string
    {
        $lock = Cache::lock("zoom-refresh:{$connection->id}", 30);

        try {
            $lock->block(15);
        } catch (LockTimeoutException $e) {
            throw new ZoomApiException('Timed out waiting for another worker to refresh the Zoom token.', 0, null, null, 'oauth/token');
        }

        try {
            // Another worker may have refreshed while we waited for the lock.
            $connection->refresh();

            if (! $connection->isActive()) {
                throw new ConnectionRevokedException("Zoom connection {$connection->id} was revoked while refreshing.");
            }

            if (! $force && $this->isFresh($connection)) {
                return (string) $connection->access_token;
            }

            if (empty($connection->refresh_token)) {
                $this->revoke($connection, 'No refresh token stored.');
            }

            try {
                $tokens = app(ZoomApi::class)->refreshToken((string) $connection->refresh_token);
            } catch (ZoomApiException $e) {
                if ($e->httpStatus >= 400 && $e->httpStatus < 500) {
                    // invalid_grant and friends: the refresh token is dead. Only a new install fixes it.
                    $this->revoke($connection, $e->summary());
                }

                throw $e;
            }

            $connection->storeTokens($tokens);

            Log::info('zoom.token.refreshed', ['connection' => $connection->describe()]);

            return $tokens->accessToken;
        } finally {
            $lock->release();
        }
    }

    private function isFresh(ZoomConnection $connection): bool
    {
        if (empty($connection->access_token) || $connection->expires_at === null) {
            return false;
        }

        $leeway = (int) config('zoom.token_refresh_leeway_minutes', 5);

        return $connection->expires_at->isAfter(now()->addMinutes($leeway));
    }

    private function revoke(ZoomConnection $connection, string $reason): never
    {
        $connection->markRevoked($reason);

        Log::warning('zoom.token.revoked', ['connection' => $connection->describe(), 'reason' => $reason]);

        Notification::send($connection->organization->owners(), new ZoomConnectionRevoked($connection->organization, $reason));

        throw new ConnectionRevokedException("Zoom refresh failed for connection {$connection->id}: {$reason}");
    }
}
