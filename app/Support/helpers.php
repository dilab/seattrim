<?php

use App\Models\Organization;
use Laravel\Cashier\Subscription;

if (! function_exists('onDemoPlan')) {
    /** Gives a demo organization the Growth feature set without touching Stripe. */
    function onDemoPlan(Organization $organization): void
    {
        // The "demo_" stripe_id prefix is what PlanResolver recognises; the price is informational.
        $price = (string) (config('plans.tiers.growth.stripe_price') ?: 'price_growth_demo');

        Subscription::query()->create([
            'organization_id' => $organization->getKey(),
            'type' => 'default',
            'stripe_id' => 'demo_'.$organization->getKey().'_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => $price,
            'quantity' => 1,
        ]);
    }
}
