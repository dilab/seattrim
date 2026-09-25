<?php

use App\Models\Organization;
use Laravel\Cashier\Subscription;

if (! function_exists('onDemoPlan')) {
    /** Gives a demo organization the Growth feature set without touching Stripe. */
    function onDemoPlan(Organization $organization): void
    {
        $price = (string) (config('plans.tiers.growth.stripe_price') ?: 'price_growth_demo');

        if ($price === 'price_growth_demo') {
            config(['plans.tiers.growth.stripe_price' => $price]);
        }

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
