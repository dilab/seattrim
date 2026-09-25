<?php

namespace App\Billing;

use App\Models\Organization;
use App\Models\Scan;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Laravel\Cashier\Subscription;

/**
 * Which plan an organization is on, which one its seat count needs, and whether
 * paid features are currently blocked by the seat-tier grace period (brief §11).
 */
class PlanResolver
{
    public static function current(Organization $organization): Plan
    {
        // Query instead of Cashier's cached relation so a subscription created moments ago is seen.
        /** @var Subscription|null $subscription */
        $subscription = $organization->subscriptions()->where('type', 'default')->latest('id')->first();

        if ($subscription === null || ! $subscription->valid()) {
            return Plans::free();
        }

        // Public-demo organizations carry a synthetic subscription (see onDemoPlan()).
        if (str_starts_with((string) $subscription->stripe_id, 'demo_')) {
            return Plans::find('growth') ?? Plans::free();
        }

        return Plans::byStripePrice($subscription->stripe_price) ?? Plans::free();
    }

    /** Licensed seats in the latest completed scan (0 when no scan). */
    public static function detectedSeats(Organization $organization): int
    {
        return app(Tenancy::class)->runAs($organization, function () {
            return (int) (Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->first()?->total('licensed_total', 0) ?? 0);
        });
    }

    public static function required(Organization $organization): ?Plan
    {
        return Plans::requiredFor(self::detectedSeats($organization));
    }

    /** True when the current paid plan's seat limit is below the detected seats. */
    public static function overLimit(Organization $organization): bool
    {
        $plan = self::current($organization);

        return ! $plan->isFree() && ! $plan->fitsSeats(self::detectedSeats($organization));
    }

    public static function overLimitSince(Organization $organization): ?CarbonImmutable
    {
        $since = $organization->setting('billing.over_limit_since');

        return $since ? CarbonImmutable::parse((string) $since) : null;
    }

    public static function graceEndsAt(Organization $organization): ?CarbonImmutable
    {
        return self::overLimitSince($organization)?->addDays((int) config('plans.grace_days', 14));
    }

    /** Paid features stop working once the grace period after exceeding the tier has passed. */
    public static function graceExpired(Organization $organization): bool
    {
        $ends = self::graceEndsAt($organization);

        return $ends !== null && $ends->isPast();
    }

    /** Called after every completed scan to start or clear the grace clock. */
    public static function recordSeatCheck(Organization $organization): void
    {
        if (self::overLimit($organization)) {
            if (self::overLimitSince($organization) === null) {
                $organization->setSetting('billing.over_limit_since', CarbonImmutable::now()->toIso8601String());
                $organization->save();
            }
        } elseif (self::overLimitSince($organization) !== null) {
            $organization->setSetting('billing.over_limit_since', null);
            $organization->save();
        }
    }

    public static function allows(Organization $organization, string $feature): bool
    {
        $plan = self::current($organization);

        if (! $plan->allows($feature)) {
            return false;
        }

        return ! self::graceExpired($organization);
    }
}
