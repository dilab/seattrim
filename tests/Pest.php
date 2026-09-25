<?php

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Features\SupportTesting\Testable;
use Livewire\LivewireManager;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Put an organization on a paid plan by inserting a Cashier subscription row (no Stripe call).
 */
function onPaidPlan(Organization $organization, string $tier = 'growth'): Subscription
{
    $organization->forceFill(['stripe_id' => 'cus_test_'.$organization->getKey()])->save();

    return Subscription::query()->create([
        'organization_id' => $organization->getKey(),
        'type' => 'default',
        'stripe_id' => 'sub_test_'.$organization->getKey().'_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_'.$tier,
        'quantity' => 1,
    ]);
}

/**
 * Act as a member of an organization in Livewire component tests, where the
 * `organization` middleware does not run. Mirrors what EnsureCurrentOrganization does.
 */
function actingAsMemberOf(Organization $organization, User $user): Testable|LivewireManager
{
    $user->switchToOrganization($organization);
    app(Tenancy::class)->set($organization);

    return Livewire\Livewire::actingAs($user);
}
