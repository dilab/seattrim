<?php

namespace App\Jobs;

use App\Actions\License\GuardrailCheck;
use App\Enums\Bucket;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Scan\Classifier;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Licensed → Basic for one member, with a fresh guardrail check first and a
 * re-fetch afterwards to confirm. Every outcome lands on the license_actions row.
 */
class DowngradeMember implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $organizationId, public int $actionId) {}

    public function handle(Tenancy $tenancy, ZoomApi $zoom, GuardrailCheck $guardrails): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->runAs($organization, function (Organization $organization) use ($zoom, $guardrails): void {
            $action = LicenseAction::query()->find($this->actionId);

            if ($action === null || $action->status !== LicenseAction::STATUS_QUEUED) {
                return;
            }

            $member = $action->member;
            $connection = ZoomConnection::query()->first();

            if ($member === null) {
                $action->markSkipped('Member no longer exists in SeatTrim.');

                return;
            }

            if ($connection === null || ! $connection->isActive()) {
                $action->markFailed('Zoom is not connected.');

                return;
            }

            [$skip, $user] = $guardrails->forDowngrade($organization, $connection, $member);

            if ($skip !== null) {
                $action->markSkipped($skip);
                $this->syncFromUser($member, $user);

                return;
            }

            if ($action->dry_run) {
                $action->markSkipped('Dry run: would have downgraded to Basic.');

                return;
            }

            $identifier = $member->zoom_user_id ?? $member->email;

            try {
                $result = $zoom->updateUserType($connection, $identifier, ZoomUser::TYPE_BASIC);
            } catch (ZoomApiException $e) {
                if ($e->zoomCode === 200 && str_contains($e->getMessage(), 'Zoom Room')) {
                    $member->forceFill(['is_room' => true, 'eligible_for_downgrade' => false, 'bucket' => Bucket::Protected, 'protected_reasons' => array_values(array_unique([...($member->protected_reasons ?? []), Classifier::REASON_ROOM]))])->save();
                }
                $action->markFailed($e->summary(), $e->trackingId);
                Log::notice('license.downgrade.failed', ['organization' => $organization->id, 'action' => $action->id, 'error' => $e->summary()]);

                return;
            } catch (ConnectionRevokedException $e) {
                $action->markFailed('Zoom access was revoked; reconnect Zoom.');

                return;
            }

            // Confirm on fresh data. Zoom answers 204 with no body, so this is the only proof.
            try {
                $fresh = $zoom->getUser($connection, $identifier);
            } catch (ZoomApiException $e) {
                $action->markFailed('Downgrade sent but could not be confirmed: '.$e->summary(), $result->trackingId);

                return;
            }

            if ($fresh->type !== ZoomUser::TYPE_BASIC) {
                $action->markFailed("Zoom accepted the request but the user is still type {$fresh->type}.", $result->trackingId);

                return;
            }

            $this->syncFromUser($member, $fresh);
            $member->forceFill(['bucket' => Bucket::Healthy, 'eligible_for_downgrade' => false])->save();
            $action->markDone($result->trackingId);

            Log::info('license.downgraded', ['organization' => $organization->id, 'action' => $action->id, 'tracking_id' => $result->trackingId]);
        });
    }

    private function syncFromUser(ZoomMember $member, ?ZoomUser $user): void
    {
        if ($user === null) {
            return;
        }

        $member->forceFill(['type' => $user->type, 'status' => $user->status]);
        if ($user->hasBundleFields()) {
            $member->forceFill(['has_bundled_license' => $user->hasBundle(), 'bundle_known' => true]);
        }
        $member->save();
    }
}
