<?php

/*
|--------------------------------------------------------------------------
| Plans (brief §11)
|--------------------------------------------------------------------------
|
| Annual billing only. Tiers are chosen by the number of licensed seats in the
| organization's latest completed scan. Stripe price ids come from .env so the
| same code runs against test and live Stripe accounts.
|
*/

return [

    'grace_days' => 14,

    'tiers' => [
        'free' => [
            'name' => 'Free',
            'price_cents' => 0,
            'seat_limit' => null,       // scans and the report are never limited
            'stripe_price' => null,
            'features' => [],
            'admins' => 1,
        ],
        'starter' => [
            'name' => 'Starter',
            'price_cents' => 29000,
            'seat_limit' => 100,
            'stripe_price' => env('STRIPE_PRICE_STARTER'),
            'features' => ['bulk_actions', 'automation', 'digest', 'renewal_reminders', 'csv_export', 'multiple_admins'],
            'admins' => null,
        ],
        'growth' => [
            'name' => 'Growth',
            'price_cents' => 79000,
            'seat_limit' => 500,
            'stripe_price' => env('STRIPE_PRICE_GROWTH'),
            'features' => ['bulk_actions', 'automation', 'digest', 'renewal_reminders', 'csv_export', 'multiple_admins'],
            'admins' => null,
        ],
        'scale' => [
            'name' => 'Scale',
            'price_cents' => 199000,
            'seat_limit' => 2000,
            'stripe_price' => env('STRIPE_PRICE_SCALE'),
            'features' => ['bulk_actions', 'automation', 'digest', 'renewal_reminders', 'csv_export', 'multiple_admins'],
            'admins' => null,
        ],
    ],

];
