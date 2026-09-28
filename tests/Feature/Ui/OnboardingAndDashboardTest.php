<?php

use App\Enums\Role;
use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\FakeZoomClient;
use Illuminate\Support\Facades\Queue;

function ownerWithOrganization(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create();
    $user->switchToOrganization($organization);

    return [$user, $organization];
}

function scannedOrganization(User $user, Organization $organization): Scan
{
    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create());
    $scan = ScanOrganization::start($organization, Scan::TRIGGER_ONBOARDING);

    return app(Tenancy::class)->runAs($organization, fn () => Scan::query()->findOrFail($scan->id));
}

test('onboarding walks connect → billing → scan → dashboard', function () {
    [$user, $organization] = ownerWithOrganization();

    $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee('Connect your Zoom account');

    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create());
    $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee('What do you pay Zoom per seat?');

    Queue::fake();
    actingAsMemberOf($organization, $user)
        ->test('pages::onboarding')
        ->set('seat_price', '150.00')
        ->set('billing_cycle', 'annual')
        ->set('currency', 'EUR')
        ->set('renewal_date', '2027-01-15')
        ->call('saveAndScan', false)
        ->assertHasNoErrors();

    $organization->refresh();
    expect($organization->seat_price_cents)->toBe(15000)
        ->and($organization->setting('currency'))->toBe('EUR')
        ->and($organization->renewal_date?->toDateString())->toBe('2027-01-15');
    Queue::assertPushed(ScanOrganization::class, 1);

    // Scan row exists but is queued → progress screen.
    $this->actingAs($user)->get(route('onboarding'))->assertOk()->assertSee('Waiting for a worker');

    // Finish the scan and the wizard hands off to the dashboard.
    app(Tenancy::class)->runAs($organization, fn () => Scan::query()->latest('id')->firstOrFail()->forceFill(['status' => 'done', 'finished_at' => now(), 'totals' => ['buckets' => []]])->save());
    $this->actingAs($user)->get(route('onboarding'))->assertRedirect(route('dashboard'));
});

test('skipping billing still starts the first scan', function () {
    [$user, $organization] = ownerWithOrganization();
    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create());
    Queue::fake();

    actingAsMemberOf($organization, $user)->test('pages::onboarding')->call('saveAndScan', true)->assertHasNoErrors();

    Queue::assertPushed(ScanOrganization::class, 1);
    expect($organization->fresh()->setting('onboarding.billing_skipped_at'))->not->toBeNull();
});

test('the dashboard shows waste, seats, buckets, warnings and the honesty note', function () {
    [$user, $organization] = ownerWithOrganization();
    $organization->update(['renewal_date' => now()->addDays(45)]);
    app(FakeZoomClient::class)->withPartialUserList();
    scannedOrganization($user, $organization);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('$3,725')             // 25 reclaimable seats × $149
        ->assertSee('25 seats')
        ->assertSee('does not reduce your Zoom bill')
        ->assertSee('Unassigned')
        ->assertSee('Idle licensed')
        ->assertSee('Deactivated, still licensed')
        ->assertSee('Pending invite, licensed')
        ->assertSee('Data-quality warnings')
        ->assertSee('Some users may be missing')
        ->assertSee('reduce to')
        ->assertSee('Scan now');
});

test('the deactivated-still-licensed card is hidden when Zoom reports none', function () {
    [$user, $organization] = ownerWithOrganization();
    app(FakeZoomClient::class)->patchUser('u38_gone1', ['type' => 1]);
    scannedOrganization($user, $organization);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Pending invite, licensed')
        ->assertDontSee('Deactivated, still licensed');
});

test('scan now queues a manual scan and changing the threshold rescans', function () {
    [$user, $organization] = ownerWithOrganization();
    scannedOrganization($user, $organization);
    Queue::fake();

    actingAsMemberOf($organization, $user)->test('pages::dashboard')->call('scanNow');
    Queue::assertPushed(ScanOrganization::class, 1);

    actingAsMemberOf($organization, $user)->test('pages::dashboard')->set('threshold', 30);
    Queue::assertPushed(ScanOrganization::class, 2);
    expect($organization->fresh()->setting('scan.threshold_days'))->toBe(30);
});

test('viewers can see the dashboard but not start scans', function () {
    $viewer = User::factory()->create();
    [$owner, $organization] = ownerWithOrganization();
    $organization->addMember($viewer, Role::Viewer);
    scannedOrganization($owner, $organization);

    $viewer->switchToOrganization($organization);
    $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertDontSee('Scan now');

    actingAsMemberOf($organization, $viewer)->test('pages::dashboard')->call('scanNow')->assertForbidden();
});
