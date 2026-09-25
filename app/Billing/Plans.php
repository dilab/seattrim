<?php

namespace App\Billing;

use Illuminate\Support\Collection;

class Plans
{
    /** @return Collection<string, Plan> keyed by tier key, in ascending order */
    public static function all(): Collection
    {
        $plans = collect();

        foreach ((array) config('plans.tiers', []) as $key => $tier) {
            $plans[(string) $key] = new Plan(
                key: (string) $key,
                name: (string) ($tier['name'] ?? ucfirst((string) $key)),
                priceCents: (int) ($tier['price_cents'] ?? 0),
                seatLimit: isset($tier['seat_limit']) ? (int) $tier['seat_limit'] : null,
                stripePrice: isset($tier['stripe_price']) && $tier['stripe_price'] !== '' ? (string) $tier['stripe_price'] : null,
                features: array_values((array) ($tier['features'] ?? [])),
                admins: isset($tier['admins']) ? (int) $tier['admins'] : null,
            );
        }

        return $plans;
    }

    public static function find(string $key): ?Plan
    {
        return self::all()->get($key);
    }

    public static function free(): Plan
    {
        return self::find('free') ?? new Plan('free', 'Free', 0, null, null, [], 1);
    }

    /** @return Collection<string, Plan> */
    public static function paid(): Collection
    {
        return self::all()->filter(fn (Plan $p) => ! $p->isFree());
    }

    public static function byStripePrice(?string $priceId): ?Plan
    {
        if ($priceId === null) {
            return null;
        }

        return self::all()->first(fn (Plan $p) => $p->stripePrice !== null && $p->stripePrice === $priceId);
    }

    /** The cheapest paid tier whose seat limit covers the given licensed-seat count. */
    public static function requiredFor(int $seats): ?Plan
    {
        return self::paid()->sortBy('seatLimit')->first(fn (Plan $p) => $p->fitsSeats($seats));
    }
}
