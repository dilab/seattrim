<?php

use App\Automation\AutomationSettings;
use App\Billing\PlanResolver;
use App\Billing\Plans;
use App\Enums\Role;
use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Laravel\Cashier\Subscription;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->user)->create();
    $this->user->switchToOrganization($this->organization);
});

function subscribe(Organization $organization, string $price, string $status = 'active'): Subscription
{
    $organization->forceFill(['stripe_id' => 'cus_'.$organization->id])->save();

    return Subscription::query()->create([
        'organization_id' => $organization->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.$organization->id.'_'.uniqid(),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
    ]);
}

function scanWithSeats(Organization $organization, int $licensed): void
{
    app(Tenancy::class)->runAs($organization, fn () => Scan::query()->create(['trigger' => 'manual', 'status' => 'done', 'finished_at' => now(), 'totals' => ['licensed_total' => $licensed]]));
}

test('tiers are chosen by licensed seats', function () {
    expect(Plans::requiredFor(0)?->key)->toBe('starter')
        ->and(Plans::requiredFor(100)?->key)->toBe('starter')
        ->and(Plans::requiredFor(101)?->key)->toBe('growth')
        ->and(Plans::requiredFor(500)?->key)->toBe('growth')
        ->and(Plans::requiredFor(1999)?->key)->toBe('scale')
        ->and(Plans::requiredFor(2001))->toBeNull()
        ->and(Plans::byStripePrice('price_growth')?->name)->toBe('Growth')
        ->and(Plans::free()->allows(Features::BULK_ACTIONS))->toBeFalse()
        ->and(Plans::find('starter')?->allows(Features::AUTOMATION))->toBeTrue();
});

test('the free plan gates paid features but never the scan or report', function () {
    expect(PlanResolver::current($this->organization)->isFree())->toBeTrue();
    foreach ([Features::BULK_ACTIONS, Features::AUTOMATION, Features::DIGEST, Features::RENEWAL_REMINDERS, Features::CSV_EXPORT, Features::MULTIPLE_ADMINS] as $feature) {
        expect(Features::allows($this->organization, $feature))->toBeFalse($feature);
    }

    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    $scan = ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
    expect(app(Tenancy::class)->runAs($this->organization, fn () => Scan::query()->find($scan->id)->status))->toBe('done');

    $this->actingAs($this->user)->get(route('audit.export'))->assertForbidden();
    actingAsMemberOf($this->organization, $this->user)->test('pages::automation')->set('enabled', true)->call('save');
    expect(AutomationSettings::for($this->organization->fresh())->enabled)->toBeFalse();
});

test('an active subscription unlocks features; cancelled or past-due ones fall back to free', function () {
    $sub = subscribe($this->organization, 'price_starter');
    expect(PlanResolver::current($this->organization)->key)->toBe('starter')
        ->and(Features::allows($this->organization, Features::BULK_ACTIONS))->toBeTrue();

    $sub->forceFill(['stripe_status' => 'canceled', 'ends_at' => now()->subDay()])->save();
    expect(PlanResolver::current($this->organization->fresh())->isFree())->toBeTrue();

    $sub->forceFill(['stripe_status' => 'unpaid', 'ends_at' => null])->save();
    expect(PlanResolver::current($this->organization->fresh())->isFree())->toBeTrue();
});

test('outgrowing the tier starts a 14-day grace period, after which paid features pause', function () {
    subscribe($this->organization, 'price_starter');
    scanWithSeats($this->organization, 150);

    PlanResolver::recordSeatCheck($this->organization);
    $this->organization->refresh();

    expect(PlanResolver::overLimit($this->organization))->toBeTrue()
        ->and(PlanResolver::required($this->organization)?->key)->toBe('growth')
        ->and(PlanResolver::graceEndsAt($this->organization)?->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and(Features::allows($this->organization, Features::BULK_ACTIONS))->toBeTrue();

    $this->actingAs($this->user)->get(route('dashboard'))->assertRedirect(); // no connection → onboarding, banner not needed here
    $this->actingAs($this->user)->get(route('billing'))->assertOk()->assertSee('outgrown')->assertSee('data-test="over-limit-banner"', false);

    $this->travelTo(CarbonImmutable::now()->addDays(15));
    expect(PlanResolver::graceExpired($this->organization))->toBeTrue()
        ->and(Features::allows($this->organization, Features::BULK_ACTIONS))->toBeFalse()
        ->and(Features::allows($this->organization, Features::AUTOMATION))->toBeFalse();
    $this->actingAs($this->user)->get(route('billing'))->assertOk()->assertSee('grace period ended');

    // Upgrading (or seats dropping) clears the clock.
    scanWithSeats($this->organization, 90);
    PlanResolver::recordSeatCheck($this->organization);
    expect(PlanResolver::overLimitSince($this->organization->fresh()))->toBeNull()
        ->and(Features::allows($this->organization->fresh(), Features::BULK_ACTIONS))->toBeTrue();
});

test('the free plan allows one managing member; paid plans allow more', function () {
    $second = User::factory()->create(['email' => 'second@example.com']);

    actingAsMemberOf($this->organization, $this->user)->test('pages::settings.members')
        ->set('email', 'second@example.com')->set('role', 'admin')->call('add')->assertHasErrors(['role']);
    actingAsMemberOf($this->organization, $this->user)->test('pages::settings.members')
        ->set('email', 'second@example.com')->set('role', 'viewer')->call('add')->assertHasNoErrors();
    expect($second->roleIn($this->organization))->toBe(Role::Viewer);

    subscribe($this->organization, 'price_growth');
    $membership = $this->organization->memberships()->where('user_id', $second->id)->firstOrFail();
    actingAsMemberOf($this->organization, $this->user)->test('pages::settings.members')->call('changeRole', $membership->id, 'admin');
    expect($second->roleIn($this->organization))->toBe(Role::Admin);
});

test('the billing page lists plans, marks the current one, and only owners see checkout', function () {
    config(['cashier.key' => null, 'cashier.secret' => null]);
    $this->actingAs($this->user)->get(route('billing'))
        ->assertOk()
        ->assertSee('Current plan: Free')
        ->assertSee('$290/yr')->assertSee('$790/yr')->assertSee('$1,990/yr')
        ->assertSee('Stripe is not configured');

    $admin = User::factory()->create();
    $this->organization->addMember($admin, Role::Admin);
    $admin->switchToOrganization($this->organization);
    $this->actingAs($admin)->get(route('billing'))->assertOk()->assertSee('Only an owner can change the plan');
    $this->actingAs($admin)->post(route('billing.checkout', 'starter'))->assertForbidden();
    $this->actingAs($this->user)->post(route('billing.checkout', 'nope'))->assertNotFound();
    $this->actingAs($this->user)->post(route('billing.checkout', 'starter'))->assertRedirect(route('billing'))->assertSessionHas('billing.error');
    $this->actingAs($this->user)->get(route('billing.portal'))->assertRedirect(route('billing'))->assertSessionHas('billing.error');
});
