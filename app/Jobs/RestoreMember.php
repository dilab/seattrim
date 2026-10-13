<?php

namespace App\Jobs;

use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\LicenseChange;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Basic → Licensed. Fails with a clear message when Zoom has no free seat
 * (error 2034/2038/3412), which is the honest signal that the admin must buy one.
 *
 * When the matching downgrade recorded a bundle option (license_actions.bundle_before),
 * the bundle is put back too: `type=2` alone does that on a Workplace-base-plan account;
 * otherwise a second PATCH re-applies `zoom_one_type` / `plan_united_type`. The seat is
 * what matters, so the action is still "done" when the bundle cannot be re-applied; the
 * reason says so.
 */
class RestoreMember implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public function __construct(public int $organizationId, public int $actionId) {}

    /** Crash outside our own handling (timeout, lost DB): the audit row must never stay "queued". */
    public function failed(?\Throwable $e): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        app(Tenancy::class)->runAs($organization, function () use ($e): void {
            LicenseAction::query()->whereKey($this->actionId)->where('status', LicenseAction::STATUS_QUEUED)
                ->update(['status' => LicenseAction::STATUS_FAILED, 'reason' => 'Job crashed: '.($e?->getMessage() ?? 'unknown error'), 'performed_at' => now()]);
        });
    }

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
                $member->syncFromZoomUser($current);
                $action->markSkipped('User is already Licensed.');

                return;
            }

            try {
                $result = $zoom->updateUserLicense($connection, $identifier, LicenseChange::type(ZoomUser::TYPE_LICENSED));
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

            $wanted = LicenseChange::fromBundleOption($action->bundle_before ?? []);

            if ($wanted === null) {
                $member->syncFromZoomUser($fresh);
                $action->markDone($result->trackingId);

                return;
            }

            if ($fresh->hasBundle()) {
                $member->syncFromZoomUser($fresh);
                $action->markDone($result->trackingId, 'Restored Licensed; the bundle came back with the seat.');

                return;
            }

            // The seat is back but the bundle is not: re-apply the recorded option through its own field.
            try {
                $second = $zoom->updateUserLicense($connection, $identifier, $wanted);
            } catch (ZoomApiException $e) {
                $member->syncFromZoomUser($fresh);
                $action->markDone($result->trackingId, 'Licensed seat restored, but the bundle option ('.$wanted->describe().') could not be re-applied: '.$e->summary());

                return;
            } catch (ConnectionRevokedException) {
                $member->syncFromZoomUser($fresh);
                $action->markDone($result->trackingId, 'Licensed seat restored, but Zoom access was revoked before the bundle option ('.$wanted->describe().') could be re-applied; reconnect Zoom.');

                return;
            }

            try {
                $fresh = $zoom->getUser($connection, $identifier);
            } catch (ZoomApiException $e) {
                $action->markDone($second->trackingId, 'Licensed seat restored and '.$wanted->describe().' sent, but the bundle could not be confirmed: '.$e->summary());

                return;
            }

            $member->syncFromZoomUser($fresh);
            $action->markDone($second->trackingId, $fresh->hasBundle()
                ? 'Restored Licensed and re-applied '.$wanted->describe().'.'
                : 'Licensed seat restored, but Zoom accepted '.$wanted->describe().' without applying it; assign the bundle in Zoom User Management.');
        });
    }
}
