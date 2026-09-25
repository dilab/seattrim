<?php

namespace App\Jobs;

use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Basic → Licensed. Fails with a clear message when Zoom has no free seat
 * (error 2034/2038/3412), which is the honest signal that the admin must buy one.
 */
class RestoreMember implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $organizationId, public int $actionId) {}

    public function handle(Tenancy $tenancy, ZoomApi $zoom): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->runAs($organization, function (Organization $organization) use ($zoom): void {
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

            $identifier = $member->zoom_user_id ?? $member->email;

            try {
                $current = $zoom->getUser($connection, $identifier);
            } catch (ZoomApiException $e) {
                $action->markFailed('Could not read the user: '.$e->summary(), $e->trackingId);

                return;
            }

            if ($current->isLicensed()) {
                $member->forceFill(['type' => ZoomUser::TYPE_LICENSED])->save();
                $action->markSkipped('User is already Licensed.');

                return;
            }

            try {
                $result = $zoom->updateUserType($connection, $identifier, ZoomUser::TYPE_LICENSED);
            } catch (ZoomApiException $e) {
                $message = in_array($e->zoomCode, [2034, 2038, 3412], true)
                    ? 'No free Licensed seat: '.$e->summary().' Buy a seat in Zoom Billing or free one first.'
                    : $e->summary();
                $action->markFailed($message, $e->trackingId);

                return;
            } catch (ConnectionRevokedException) {
                $action->markFailed('Zoom access was revoked; reconnect Zoom.');

                return;
            }

            try {
                $fresh = $zoom->getUser($connection, $identifier);
            } catch (ZoomApiException $e) {
                $action->markFailed('Restore sent but could not be confirmed: '.$e->summary(), $result->trackingId);

                return;
            }

            if (! $fresh->isLicensed()) {
                $action->markFailed("Zoom accepted the request but the user is still type {$fresh->type}.", $result->trackingId);

                return;
            }

            $member->forceFill(['type' => ZoomUser::TYPE_LICENSED, 'status' => $fresh->status])->save();
            $action->markDone($result->trackingId);
        });
    }
}
