<?php

namespace App\Actions\License;

use App\Models\Exclusion;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Scan\Classifier;
use App\Scan\ExclusionMatcher;
use App\Scan\MemberFacts;
use App\Scan\ScanRunner;
use App\Scan\ScanSettings;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ZoomApiException;
use Carbon\CarbonImmutable;

/**
 * Re-fetches a member from Zoom right before acting and re-runs every guardrail
 * on that fresh data (brief §7). Returns null when the downgrade may proceed,
 * otherwise a human-readable reason to skip.
 */
class GuardrailCheck
{
    public function __construct(private readonly ZoomApi $zoom) {}

    /** @return array{0: ?string, 1: ?ZoomUser} [skip reason or null, fresh user] */
    public function forDowngrade(Organization $organization, ZoomConnection $connection, ZoomMember $member): array
    {
        $identifier = $member->zoom_user_id ?? $member->email;

        try {
            $user = $this->zoom->getUser($connection, $identifier);
        } catch (ZoomApiException $e) {
            if ($e->isNotFound()) {
                $member->forceFill(['removed_at' => now()])->save();

                return ['User no longer exists in Zoom ('.$e->summary().')', null];
            }

            return ['Could not re-check the user in Zoom: '.$e->summary(), null];
        }

        if (! $user->isLicensed()) {
            return ['User is no longer Licensed (type '.$user->type.'), nothing to downgrade', $user];
        }

        $settings = ScanSettings::for($organization);
        $classifier = new Classifier($settings);
        $now = CarbonImmutable::now();

        $addOns = $member->add_ons ?? [];
        if ($connection->hasScope(ScanRunner::SCOPE_SETTINGS) && $user->id !== null) {
            try {
                $addOns = $this->zoom->userFeatures($connection, $user->id)->enabledAddOns;
            } catch (ZoomApiException $e) {
                return ['Could not re-check add-ons: '.$e->summary(), $user];
            }
        }

        $upcoming = null;
        if (! $user->isDeactivated() && $user->id !== null) {
            if ($connection->hasScope(ScanRunner::SCOPE_MEETINGS)) {
                try {
                    $upcoming = count($this->zoom->upcomingMeetings($connection, $user->id));
                } catch (ZoomApiException $e) {
                    return ['Could not re-check upcoming meetings: '.$e->summary(), $user];
                }
            }
        }

        $facts = new MemberFacts(
            status: $user->status,
            type: $user->type,
            isOwnerOrAdmin: $user->isOwnerOrAdmin(),
            isRoom: $member->is_room,
            hasBundle: $user->hasBundle(),
            bundleKnown: $user->hasBundleFields() || $member->bundle_known,
            addOns: $addOns,
            upcomingMeetings: $upcoming,
            createdAt: $user->userCreatedAt ?? $member->created_at_zoom?->toImmutable(),
            lastLoginAt: $user->lastLoginAt,
            exclusionReason: (new ExclusionMatcher(Exclusion::query()->get()))->reasonFor($user->email, $user->groupIds),
            excludedUntil: $member->excluded_until?->toImmutable(),
            hostingKnown: $member->last_hosted_window !== 'unknown',
            meetingsByWindow: $member->meetings_by_window ?? [],
        );

        // Pending invites carry no id, so upcoming-meeting and add-on checks are impossible; the
        // classifier already treats that as acceptable for pending users.
        $reasons = $classifier->guardrails($facts, $now);

        if ($reasons !== []) {
            return ['Guardrail: '.implode('; ', $reasons), $user];
        }

        if ($user->status === ZoomUser::STATUS_ACTIVE && ! $classifier->isIdle($facts)) {
            return ['User hosted a meeting within the threshold since the last scan', $user];
        }

        return [null, $user];
    }
}
