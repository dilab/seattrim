<?php

namespace App\Jobs;

use App\Actions\License\GuardrailCheck;
use App\Enums\Bucket;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Scan\Classifier;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\LicenseChange;
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
 *
 * Bundled users (Zoom Workplace / Zoom One / Zoom United) are downgraded in up to
 * two PATCH calls, because Zoom accepts only one license field per request:
 * `type=1` first (enough when the bundle is the account's base plan), then, if the
 * user is still Licensed with the bundle on, `zoom_one_type=0` or
 * `plan_united_type="none"`. The bundle option found on the user is recorded on the
 * audit row so RestoreMember can put it back. See docs/zoom-api-notes.md §4.
 */
class DowngradeMember implements ShouldQueue
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
                if ($user !== null) {
                    $member->syncFromZoomUser($user);
                }

                return;
            }

            if ($action->dry_run) {
                $action->markSkipped('Dry run: would have downgraded to Basic.');

                return;
            }

            /** @var ZoomUser $user */
            $identifier = $member->zoom_user_id ?? $member->email;
            $bundleBefore = $user->bundleOption();
            $action->forceFill(['bundle_before' => $bundleBefore])->save();

            // Step 1: type=1. On a Workplace-base-plan account this also releases the bundle.
            $first = LicenseChange::type(ZoomUser::TYPE_BASIC);
            $fresh = null;
            $result = null;

            try {
                $result = $zoom->updateUserLicense($connection, $identifier, $first);
            } catch (ZoomApiException $e) {
                if ($e->zoomCode === 200 && str_contains($e->getMessage(), 'Zoom Room')) {
                    $member->forceFill(['is_room' => true, 'eligible_for_downgrade' => false, 'bucket' => Bucket::Protected, 'protected_reasons' => array_values(array_unique([...($member->protected_reasons ?? []), Classifier::REASON_ROOM]))])->save();
                }

                // Error 300 on a bundled user ("can't change type … at the same time" and friends) means the
                // bundle has to be turned off through its own field instead. Zoom Events is a real refusal.
                $fallBack = $e->zoomCode === 300 && $bundleBefore !== null && ! str_contains($e->getMessage(), 'Zoom Events');

                if (! $fallBack) {
                    $this->fail($organization, $action, $this->describeFailure($e), $e->trackingId);

                    return;
                }

                $fresh = $user;
            } catch (ConnectionRevokedException) {
                $action->markFailed('Zoom access was revoked; reconnect Zoom.');

                return;
            }

            $path = $first->describe();

            if ($fresh === null) {
                // Confirm on fresh data. Zoom answers 204 with no body, so this is the only proof.
                try {
                    $fresh = $zoom->getUser($connection, $identifier);
                } catch (ZoomApiException $e) {
                    $action->markFailed('Downgrade sent but could not be confirmed: '.$e->summary(), $result->trackingId);

                    return;
                }
            }

            // Step 2: still Licensed with the bundle on → turn the bundle off through its own field.
            if ($fresh->type !== ZoomUser::TYPE_BASIC) {
                $off = $fresh->isLicensed() ? $fresh->bundleOffChange() : null;

                if ($off === null) {
                    $action->markFailed("Zoom accepted the request but the user is still type {$fresh->type}.", $result?->trackingId);

                    return;
                }

                try {
                    $result = $zoom->updateUserLicense($connection, $identifier, $off);
                } catch (ZoomApiException $e) {
                    $this->fail($organization, $action, $this->describeFailure($e), $e->trackingId);

                    return;
                } catch (ConnectionRevokedException) {
                    $action->markFailed('Zoom access was revoked; reconnect Zoom.');

                    return;
                }

                $path = $off->describe();

                try {
                    $fresh = $zoom->getUser($connection, $identifier);
                } catch (ZoomApiException $e) {
                    $action->markFailed('Downgrade sent but could not be confirmed: '.$e->summary(), $result->trackingId);

                    return;
                }

                if ($fresh->type !== ZoomUser::TYPE_BASIC) {
                    $member->syncFromZoomUser($fresh);
                    $action->markFailed("Zoom turned the bundle off but the user is still Licensed (type {$fresh->type}); run Downgrade again to move them to Basic.", $result->trackingId);

                    return;
                }
            }

            $member->syncFromZoomUser($fresh);
            $member->forceFill(['bucket' => Bucket::Healthy, 'eligible_for_downgrade' => false])->save();
            $action->markDone($result?->trackingId, $bundleBefore === null ? null : "Downgraded via {$path}; bundle option recorded for restore.");

            Log::info('license.downgraded', ['organization' => $organization->id, 'action' => $action->id, 'path' => $path, 'tracking_id' => $result?->trackingId]);
        });
    }

    private function fail(Organization $organization, LicenseAction $action, string $reason, ?string $trackingId): void
    {
        $action->markFailed($reason, $trackingId);
        Log::notice('license.downgrade.failed', ['organization' => $organization->id, 'action' => $action->id, 'error' => $reason]);
    }

    /** Zoom's own text, with the two license-pool errors explained (docs/zoom-api-notes.md §7). */
    private function describeFailure(ZoomApiException $e): string
    {
        return match ($e->zoomCode) {
            2039 => 'Zoom has no free Basic license to move this user onto: '.$e->summary().' Buy or free a Basic seat in Zoom Billing first.',
            2033, 3412 => 'Zoom has reached its maximum number of Basic users: '.$e->summary(),
            default => $e->summary(),
        };
    }
}
