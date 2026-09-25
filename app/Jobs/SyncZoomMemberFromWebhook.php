<?php

namespace App\Jobs;

use App\Enums\Bucket;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Scan\MemberUpserter;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Keeps zoom_members fresh between scans for user.created/updated/activated/
 * deactivated/deleted. Buckets are only recomputed by the next scan, except
 * that a user who is no longer licensed drops out of the waste buckets at once.
 */
class SyncZoomMemberFromWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> seconds */
    public array $backoff = [60, 600, 3600];

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

            if ($userId === '') {
                return;
            }

            if ($this->event === 'user.deleted') {
                ZoomMember::query()->where('zoom_user_id', $userId)->update(['removed_at' => now()]);
                Log::info('zoom.member.webhook.removed', ['organization' => $organization->id]);

                return;
            }

            try {
                $user = $zoom->getUser($connection, $userId);
            } catch (ZoomApiException $e) {
                if ($e->isNotFound()) {
                    ZoomMember::query()->where('zoom_user_id', $userId)->update(['removed_at' => now()]);
                }
                Log::notice('zoom.member.webhook.failed', ['event' => $this->event, 'organization' => $organization->id, 'error' => $e->summary()]);

                return;
            }

            $member = (new MemberUpserter)->upsert($user);

            if (! $member->isLicensed() && $member->bucket->isWaste()) {
                $member->forceFill(['bucket' => Bucket::Healthy, 'eligible_for_downgrade' => false])->save();
            }
        });
    }
}
