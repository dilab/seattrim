<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;

test('the connection page offers to connect when nothing is connected', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create();
    $user->switchToOrganization($organization);

    $this->actingAs($user)->get(route('connection.edit'))
        ->assertOk()
        ->assertSee('Not connected')
        ->assertSee('Connect Zoom')
        ->assertSee('Demo mode');
});

test('the connection page shows status and scopes, and only owners can disconnect', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->withMember($admin, Role::Admin)->create();
    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create(['zoom_account_id' => 'acct-1', 'installer_email' => 'dana@example.edu', 'scopes' => ['user:read:list_users:admin']]));

    $owner->switchToOrganization($organization);
    $this->actingAs($owner)->get(route('connection.edit'))
        ->assertOk()
        ->assertSee('acct-1')
        ->assertSee('dana@example.edu')
        ->assertSee('user:read:list_users:admin')
        ->assertSee('Missing scopes')
        ->assertSee('Disconnect and delete data');

    $admin->switchToOrganization($organization);
    $this->actingAs($admin)->get(route('connection.edit'))
        ->assertOk()
        ->assertDontSee('Disconnect and delete data');

    actingAsMemberOf($organization, $admin)->test('pages::connection')->set('confirmation', 'DELETE')->call('disconnect')->assertForbidden();

    actingAsMemberOf($organization, $owner)->test('pages::connection')->set('confirmation', 'nope')->call('disconnect')->assertHasErrors(['confirmation']);
    expect(ZoomConnection::query()->allOrganizations()->count())->toBe(1);

    actingAsMemberOf($organization, $owner)->test('pages::connection')->set('confirmation', 'DELETE')->call('disconnect')->assertHasNoErrors();
    expect(ZoomConnection::query()->allOrganizations()->count())->toBe(0)
        ->and($organization->fresh()->setting('zoom.disconnect_reason'))->toBe('manual');
});
