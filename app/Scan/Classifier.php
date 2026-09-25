<?php

namespace App\Scan;

use App\Enums\Bucket;
use Carbon\CarbonImmutable;

/**
 * Pure bucket logic (brief §6.4–6.5). No IO. Unit-tested exhaustively.
 *
 * Order of decisions for a licensed member:
 *   deactivated → pending → idle-with-guardrail (protected) → idle → healthy.
 * Non-licensed members are always healthy. Guardrail reasons are computed for
 * every licensed member so the UI can explain why an action is unavailable,
 * and eligibleForDowngrade is true only when no reason applies.
 */
class Classifier
{
    public const REASON_BUNDLE = 'Workplace/United bundle: downgrading would break Phone and other bundled products';

    public const REASON_BUNDLE_UNKNOWN = 'license bundle unknown';

    public const REASON_ROLE = 'Zoom account owner or admin';

    public const REASON_ROOM = 'Zoom Room';

    public const REASON_UPCOMING = 'has upcoming scheduled meetings';

    public const REASON_UPCOMING_UNKNOWN = 'upcoming meetings could not be checked';

    public const REASON_NEW = 'account created less than %d days ago';

    public const REASON_INVITED_RECENTLY = 'invited less than %d days ago';

    public const REASON_EXCLUDED = 'excluded: %s';

    public const REASON_EXCLUDED_UNTIL = 'kept by the user until %s';

    public function __construct(private readonly ScanSettings $settings) {}

    public function classify(MemberFacts $facts, ?CarbonImmutable $now = null): Classification
    {
        $now ??= CarbonImmutable::now();
        $lastHosted = $this->lastHostedWindow($facts);

        if (! $facts->isLicensed()) {
            return new Classification(Bucket::Healthy, [], $lastHosted, false, false);
        }

        $reasons = $this->guardrails($facts, $now);
        $eligible = $reasons === [];

        if ($facts->isDeactivated()) {
            return new Classification(Bucket::DeactivatedLicensed, $reasons, $lastHosted, true, $eligible);
        }

        if ($facts->isPending()) {
            return new Classification(Bucket::PendingLicensed, $reasons, $lastHosted, true, $eligible);
        }

        $idle = $this->isIdle($facts, $lastHosted);

        if ($idle && ! $eligible) {
            return new Classification(Bucket::Protected, $reasons, $lastHosted, true, false);
        }

        if ($idle) {
            return new Classification(Bucket::IdleLicensed, [], $lastHosted, true, true);
        }

        // Healthy hosts keep their reasons for display, but are never downgrade candidates.
        return new Classification(Bucket::Healthy, $reasons, $lastHosted, false, false);
    }

    /**
     * Most recent window with ≥1 hosted meeting, "none", or "unknown" when the
     * report could not be fetched.
     */
    public function lastHostedWindow(MemberFacts $facts): string
    {
        if (! $facts->hostingKnown) {
            return Window::UNKNOWN;
        }

        $windows = $facts->meetingsByWindow;
        uksort($windows, fn (string $a, string $b) => (Window::startOf($a) ?? 0) <=> (Window::startOf($b) ?? 0));

        foreach ($windows as $label => $meetings) {
            if ($meetings >= 1) {
                return $label;
            }
        }

        return Window::NONE;
    }

    /**
     * Idle = no hosted meeting within the threshold. Unknown hosting data is
     * never treated as idle (we would rather under-report than downgrade a host).
     */
    public function isIdle(MemberFacts $facts, ?string $lastHosted = null): bool
    {
        $lastHosted ??= $this->lastHostedWindow($facts);

        if ($lastHosted === Window::UNKNOWN) {
            return false;
        }

        if ($lastHosted === Window::NONE) {
            return true;
        }

        return (Window::startOf($lastHosted) ?? 0) >= $this->settings->thresholdDays;
    }

    /**
     * Every guardrail that applies, in a stable human-readable order.
     *
     * @return array<int, string>
     */
    public function guardrails(MemberFacts $facts, CarbonImmutable $now): array
    {
        $reasons = [];

        if ($facts->isRoom) {
            $reasons[] = self::REASON_ROOM;
        }

        if ($facts->isOwnerOrAdmin) {
            $reasons[] = self::REASON_ROLE;
        }

        if ($facts->hasBundle) {
            $reasons[] = self::REASON_BUNDLE;
        } elseif (! $facts->bundleKnown) {
            $reasons[] = self::REASON_BUNDLE_UNKNOWN;
        }

        foreach ($facts->addOns as $addOn) {
            $reasons[] = self::describeAddOn($addOn);
        }

        if (! $facts->isDeactivated()) {
            if ($facts->upcomingMeetings === null && ! $facts->isPending()) {
                $reasons[] = self::REASON_UPCOMING_UNKNOWN;
            } elseif ($facts->upcomingMeetings > 0) {
                $reasons[] = self::REASON_UPCOMING;
            }
        }

        if ($facts->exclusionReason !== null) {
            $reasons[] = sprintf(self::REASON_EXCLUDED, $facts->exclusionReason);
        }

        if ($facts->excludedUntil !== null && $facts->excludedUntil->isAfter($now)) {
            $reasons[] = sprintf(self::REASON_EXCLUDED_UNTIL, $facts->excludedUntil->toDateString());
        }

        if (! $facts->isDeactivated() && $facts->createdAt !== null && $this->settings->newHireDays > 0
            && $facts->createdAt->isAfter($now->subDays($this->settings->newHireDays))) {
            $reasons[] = sprintf($facts->isPending() ? self::REASON_INVITED_RECENTLY : self::REASON_NEW, $this->settings->newHireDays);
        }

        return array_values(array_unique($reasons));
    }

    public static function describeAddOn(string $key): string
    {
        return match ($key) {
            'zoom_phone' => 'has Zoom Phone',
            'webinar' => 'has a Webinar add-on',
            'large_meeting' => 'has a Large Meeting add-on',
            'zoom_events', 'zoom_events_unlimited' => 'has a Zoom Events add-on',
            default => 'has add-on '.str_replace('_', ' ', preg_replace('/^zoom_/', '', $key) ?? $key),
        };
    }
}
