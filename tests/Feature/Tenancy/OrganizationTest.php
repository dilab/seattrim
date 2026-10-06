<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

test('a user without an organization is sent to create one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('organizations.create'));
});

test('creating an organization makes the user its owner and current organization', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::organizations.create')
        ->set('name', 'Acme School District')
        ->set('timezone', 'America/Chicago')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $organization = Organization::query()->where('name', 'Acme School District')->firstOrFail();

    expect($organization->timezone)->toBe('America/Chicago')
        ->and($organization->seat_price_cents)->toBe(14900)
        ->and($user->fresh()->current_organization_id)->toBe($organization->id)
        ->and($user->roleIn($organization))->toBe(Role::Owner);
});

test('the dashboard loads for a member and shows the organization name', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create(['name' => 'Northwind Nonprofit']);
    $user->switchToOrganization($organization);

    $this->actingAs($user)->get(route('onboarding'))
        ->assertOk()
        ->assertSee('Northwind Nonprofit');
});

test('a user cannot switch to an organization they do not belong to', function () {
    $user = User::factory()->create();
    $mine = Organization::factory()->withMember($user)->create();
    $theirs = Organization::factory()->withMember(User::factory()->create())->create();
    $user->switchToOrganization($mine);

    $this->actingAs($user)->post(route('organizations.switch', $theirs))->assertForbidden();

    expect($user->fresh()->current_organization_id)->toBe($mine->id);
});

test('a stale current organization is replaced by one the user belongs to', function () {
    $user = User::factory()->create();
    $mine = Organization::factory()->withMember($user)->create();
    $theirs = Organization::factory()->withMember(User::factory()->create())->create();
    $user->forceFill(['current_organization_id' => $theirs->id])->save();

    $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee($mine->name)->assertDontSee($theirs->name);

    expect($user->fresh()->current_organization_id)->toBe($mine->id);
});

test('owners and admins can update organization settings, viewers cannot', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->withMember($viewer, Role::Viewer)->create();
    $owner->switchToOrganization($organization);
    $viewer->switchToOrganization($organization);

    actingAsMemberOf($organization, $owner)
        ->test('pages::settings.organization')
        ->set('name', 'Renamed')
        ->set('seat_price', '15.99')
        ->set('billing_cycle', 'monthly')
        ->set('renewal_date', '2027-03-01')
        ->call('save')
        ->assertHasNoErrors();

    $organization->refresh();
    expect($organization->name)->toBe('Renamed')
        ->and($organization->seat_price_cents)->toBe(1599)
        ->and($organization->billing_cycle)->toBe('monthly')
        ->and($organization->renewal_date?->toDateString())->toBe('2027-03-01')
        ->and($organization->annualSeatPriceCents())->toBe(19188);

    actingAsMemberOf($organization, $viewer)
        ->test('pages::settings.organization')
        ->set('name', 'Hacked')
        ->call('save')
        ->assertForbidden();

    expect($organization->fresh()->name)->toBe('Renamed');
});

test('admins can add members by email and change roles', function () {
    $admin = User::factory()->create();
    $newcomer = User::factory()->create(['email' => 'new@example.com']);
    $organization = Organization::factory()->withMember($admin, Role::Admin)->withMember(User::factory()->create(), Role::Owner)->create();
    onPaidPlan($organization);
    $admin->switchToOrganization($organization);

    actingAsMemberOf($organization, $admin)
        ->test('pages::settings.members')
        ->set('email', 'nobody@example.com')
        ->set('role', 'viewer')
        ->call('add')
        ->assertHasErrors(['email']);

    actingAsMemberOf($organization, $admin)
        ->test('pages::settings.members')
        ->set('email', 'new@example.com')
        ->set('role', 'viewer')
        ->call('add')
        ->assertHasNoErrors();

    expect($newcomer->roleIn($organization))->toBe(Role::Viewer);

    $membership = $organization->memberships()->where('user_id', $newcomer->id)->firstOrFail();

    actingAsMemberOf($organization, $admin)
        ->test('pages::settings.members')
        ->call('changeRole', $membership->id, 'admin');

    expect($newcomer->roleIn($organization))->toBe(Role::Admin);

    // An admin cannot promote to owner.
    actingAsMemberOf($organization, $admin)
        ->test('pages::settings.members')
        ->call('changeRole', $membership->id, 'owner');

    expect($newcomer->roleIn($organization))->toBe(Role::Admin);
});

test('the last owner cannot be demoted or removed', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->create();
    $owner->switchToOrganization($organization);
    $membership = $organization->memberships()->firstOrFail();

    actingAsMemberOf($organization, $owner)->test('pages::settings.members')->call('changeRole', $membership->id, 'viewer');
    expect($owner->roleIn($organization))->toBe(Role::Owner);

    actingAsMemberOf($organization, $owner)->test('pages::settings.members')->call('remove', $membership->id);
    expect($organization->hasMember($owner))->toBeTrue();
});

test('viewers cannot manage members', function () {
    $viewer = User::factory()->create();
    $organization = Organization::factory()->withMember(User::factory()->create())->withMember($viewer, Role::Viewer)->create();
    $viewer->switchToOrganization($organization);

    actingAsMemberOf($organization, $viewer)
        ->test('pages::settings.members')
        ->set('email', 'x@example.com')
        ->call('add')
        ->assertForbidden();
});

test('livewire action requests keep the current organization after replaying the middleware', function () {
    expect(app(\Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(\App\Http\Middleware\EnsureCurrentOrganization::class);

    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create();
    app(\App\Tenancy\Tenancy::class)->forget();

    // Livewire replays persistent middleware to a dummy response, then runs the action.
    $request = \Illuminate\Http\Request::create('/onboarding');
    $request->setUserResolver(fn () => $user);
    \Livewire\Drawer\Utils::applyMiddleware($request, [\App\Http\Middleware\EnsureCurrentOrganization::class]);

    expect(app(\App\Tenancy\Tenancy::class)->current()?->is($organization))->toBeTrue();
});
