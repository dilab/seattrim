<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Keeps zoom_members fresh between scans for user.created/updated/activated/
 * deactivated/deleted. Filled in at M3 once the members table exists; until then
 * it only re-reads the user to prove the connection still works.
 */
class SyncZoomMemberFromWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $object */
    public function __construct(public int $organizationId, public string $event, public array $object) {}

    public function handle(Tenancy $tenancy, ZoomApi $zoom): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->runAs($organization, function (Organization $organization) use ($zoom): void {
            $connection = ZoomConnection::query()->first();

            if ($connection === null || ! $connection->isActive()) {
                return;
            }

            $userId = (string) ($this->object['id'] ?? '');

            if ($this->event === 'user.deleted' || $userId === '') {
                Log::info('zoom.member.webhook', ['event' => $this->event, 'organization' => $organization->id]);

                return;
            }

            try {
                $zoom->getUser($connection, $userId);
            } catch (ZoomApiException $e) {
                Log::notice('zoom.member.webhook.failed', ['event' => $this->event, 'organization' => $organization->id, 'error' => $e->summary()]);
            }
        });
    }
}
