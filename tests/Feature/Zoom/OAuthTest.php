<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use Illuminate\Support\Facades\DB;

function memberWithOrg(Role $role = Role::Owner): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create();
    $user->switchToOrganization($organization);

    return [$user, $organization];
}

test('connect redirects to the Zoom authorize URL with a state when the http driver is configured', function () {
    config(['zoom.driver' => 'http', 'zoom.client_id' => 'client-123', 'zoom.redirect_uri' => 'https://app.test/zoom/callback']);
    [$user] = memberWithOrg();

    $response = $this->actingAs($user)->post(route('zoom.connect'));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://zoom.us/oauth/authorize?')
        ->and($location)->toContain('response_type=code')
        ->and($location)->toContain('client_id=client-123')
        ->and($location)->toContain('redirect_uri='.urlencode('https://app.test/zoom/callback'))
        ->and($location)->toContain('state=');

    expect(session('zoom.oauth.state'))->toBeString()->toHaveLength(40);
});

test('viewers cannot start a connection', function () {
    [$user] = memberWithOrg(Role::Viewer);

    $this->actingAs($user)->post(route('zoom.connect'))->assertForbidden();
});

test('the callback rejects a mismatched state', function () {
    [$user] = memberWithOrg();

    $this->actingAs($user)->withSession(['zoom.oauth' => ['state' => 'expected', 'organization_id' => 1]])
        ->get(route('zoom.callback', ['code' => 'x', 'state' => 'wrong']))
        ->assertForbidden();

    expect(ZoomConnection::query()->allOrganizations()->count())->toBe(0);
});

test('the fake driver completes the whole flow and stores encrypted tokens', function () {
    [$user, $organization] = memberWithOrg();

    $connectResponse = $this->actingAs($user)->post(route('zoom.connect'));
    $connectResponse->assertRedirect();
    $state = session('zoom.oauth.state');

    $this->actingAs($user)->get(route('zoom.callback', ['code' => 'fake-code', 'state' => $state]))
        ->assertRedirect(route('onboarding'))
        ->assertSessionHas('zoom.connected');

    $connection = ZoomConnection::query()->allOrganizations()->where('organization_id', $organization->id)->firstOrFail();

    expect($connection->status)->toBe('active')
        ->and($connection->zoom_account_id)->toBe('demo-'.$organization->id)
        ->and($connection->installer_email)->toBe('dana.owner@example.edu')
        ->and($connection->scopes)->toBe(config('zoom.scopes'))
        ->and($connection->missingScopes())->toBe([])
        ->and($connection->expires_at?->isFuture())->toBeTrue();

    $raw = DB::table('zoom_connections')->where('id', $connection->id)->first();
    expect($raw->access_token)->not->toContain('fake-access')
        ->and($raw->refresh_token)->not->toContain('fake-refresh')
        ->and($connection->access_token)->toStartWith('fake-access');

    // The state is single-use.
    $this->actingAs($user)->get(route('zoom.callback', ['code' => 'fake-code', 'state' => $state]))->assertForbidden();
});

test('a denied authorization is reported without creating a connection', function () {
    [$user, $organization] = memberWithOrg();

    $this->actingAs($user)->withSession(['zoom.oauth' => ['state' => 's', 'organization_id' => $organization->id]])
        ->get(route('zoom.callback', ['error' => 'access_denied', 'error_description' => 'User declined', 'state' => 's']))
        ->assertRedirect(route('connection.edit'))
        ->assertSessionHas('zoom.error');

    expect(ZoomConnection::query()->allOrganizations()->count())->toBe(0);
});
