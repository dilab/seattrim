<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Data\LicenseChange;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use App\Zoom\FixtureSet;

beforeEach(function () {
    $organization = Organization::factory()->withMember(User::factory()->create())->create();
    $this->connection = app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create());
    $this->fake = app(FakeZoomClient::class);
});

test('fixtures cover every status and the documented shapes', function () {
    $active = $this->fake->listUsers($this->connection, ZoomUser::STATUS_ACTIVE);
    $inactive = $this->fake->listUsers($this->connection, ZoomUser::STATUS_INACTIVE);
    $pending = $this->fake->listUsers($this->connection, ZoomUser::STATUS_PENDING);

    expect($active->count() + $inactive->count() + $pending->count())->toBe(69)
        ->and($inactive->count())->toBe(7)
        ->and($pending->count())->toBe(5)
        ->and($pending->items[0]->id)->toBeNull()
        ->and($pending->items[0]->key())->toStartWith('email:')
        ->and($active->items[0]->isOwnerOrAdmin())->toBeTrue()
        ->and($active->isPartial())->toBeFalse();

    $summary = $this->fake->userSummary($this->connection);
    expect($summary->pending)->toBe(5)->and($summary->rooms)->toBe(2);

    $usage = $this->fake->planUsage($this->connection);
    expect($usage->unassigned())->toBe(9)->and($usage->hasBundlePlans())->toBeTrue();
});

test('date placeholders resolve relative to now', function () {
    expect(FixtureSet::resolve('@days_ago:10'))->toBe(now()->startOfDay()->subDays(10)->setTime(10, 0)->toIso8601ZuluString())
        ->and(FixtureSet::resolve('@days_ahead:2T15:30'))->toBe(now()->startOfDay()->addDays(2)->setTime(15, 30)->toIso8601ZuluString())
        ->and(FixtureSet::resolve('plain'))->toBe('plain');
});

test('the host report counts hosting days inside the window and refuses windows over a month', function () {
    $active = $this->fake->hostReport($this->connection, 'active', now()->subDays(30), now());
    $inactive = $this->fake->hostReport($this->connection, 'inactive', now()->subDays(30), now());

    $ids = array_map(fn ($r) => $r->id, $active->items);
    expect($ids)->toContain('u01_danaowner')->not->toContain('u03_leeadmin');
    expect(array_map(fn ($r) => $r->id, $inactive->items))->toContain('u03_leeadmin');

    expect(fn () => $this->fake->hostReport($this->connection, 'active', now()->subDays(60), now()))->toThrow(ZoomApiException::class);
});

test('updateUserLicense persists per account and rooms fail with error 200', function () {
    $this->fake->updateUserLicense($this->connection, 'u04_teacher1', LicenseChange::type(ZoomUser::TYPE_BASIC));
    expect($this->fake->getUser($this->connection, 'u04_teacher1')->type)->toBe(ZoomUser::TYPE_BASIC);

    $other = app(Tenancy::class)->runAs(Organization::factory()->create(), fn () => ZoomConnection::factory()->create());
    expect($this->fake->getUser($other, 'u04_teacher1')->type)->toBe(ZoomUser::TYPE_LICENSED);

    try {
        $this->fake->updateUserLicense($this->connection, 'u57_roomlibrary', LicenseChange::type(ZoomUser::TYPE_BASIC));
        $this->fail('rooms must fail');
    } catch (ZoomApiException $e) {
        expect($e->zoomCode)->toBe(200)->and($e->isPermissionDenied())->toBeTrue();
    }

    expect(fn () => $this->fake->updateUserLicense($this->connection, 'nope', LicenseChange::type(1)))->toThrow(ZoomApiException::class);
});

test('on a Workplace-base-plan account type=1 releases the bundle and type=2 brings it back', function () {
    $before = $this->fake->getUser($this->connection, 'u59_bundleworkplace1');
    expect($before->bundleOption())->toBe(['zoom_one_type' => 16]);

    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::type(ZoomUser::TYPE_BASIC));
    $after = $this->fake->getUser($this->connection, 'u59_bundleworkplace1');
    expect($after->type)->toBe(ZoomUser::TYPE_BASIC)
        ->and($after->hasBundle())->toBeFalse()
        ->and($after->hasBundleFields())->toBeTrue()
        ->and($after->bundleOption())->toBeNull();

    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::type(ZoomUser::TYPE_LICENSED));
    $restored = $this->fake->getUser($this->connection, 'u59_bundleworkplace1');
    expect($restored->type)->toBe(ZoomUser::TYPE_LICENSED)->and($restored->zoomOneType)->toBe(16);

    $this->fake->updateUserLicense($this->connection, 'u61_bundleunited', LicenseChange::type(ZoomUser::TYPE_BASIC));
    expect($this->fake->getUser($this->connection, 'u61_bundleunited')->planUnitedType)->toBeNull();
    $this->fake->updateUserLicense($this->connection, 'u61_bundleunited', LicenseChange::type(ZoomUser::TYPE_LICENSED));
    expect($this->fake->getUser($this->connection, 'u61_bundleunited')->planUnitedType)->toBe('16');
});

test('withBundleAsAddOn keeps bundled users Licensed on type=1 and frees the seat with the bundle field', function () {
    $this->fake->withBundleAsAddOn();

    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::type(ZoomUser::TYPE_BASIC));
    $still = $this->fake->getUser($this->connection, 'u59_bundleworkplace1');
    expect($still->type)->toBe(ZoomUser::TYPE_LICENSED)->and($still->hasBundle())->toBeTrue();

    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::zoomOne(0));
    $off = $this->fake->getUser($this->connection, 'u59_bundleworkplace1');
    expect($off->type)->toBe(ZoomUser::TYPE_BASIC)->and($off->hasBundle())->toBeFalse();

    // type=2 alone does not bring an add-on bundle back; the bundle field does.
    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::type(ZoomUser::TYPE_LICENSED));
    expect($this->fake->getUser($this->connection, 'u59_bundleworkplace1')->hasBundle())->toBeFalse();
    $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::zoomOne(16));
    expect($this->fake->getUser($this->connection, 'u59_bundleworkplace1')->zoomOneType)->toBe(16);

    $this->fake->updateUserLicense($this->connection, 'u61_bundleunited', LicenseChange::type(ZoomUser::TYPE_BASIC));
    expect($this->fake->getUser($this->connection, 'u61_bundleunited')->type)->toBe(ZoomUser::TYPE_LICENSED);
    $this->fake->updateUserLicense($this->connection, 'u61_bundleunited', LicenseChange::united('none'));
    $united = $this->fake->getUser($this->connection, 'u61_bundleunited');
    expect($united->type)->toBe(ZoomUser::TYPE_BASIC)->and($united->planUnitedType)->toBeNull();
});

test('zoom_one_type=0 is refused with error 300 on a base-plan bundle, and Enterprise options cannot be assigned', function () {
    try {
        $this->fake->updateUserLicense($this->connection, 'u59_bundleworkplace1', LicenseChange::zoomOne(0));
        $this->fail('expected error 300');
    } catch (ZoomApiException $e) {
        expect($e->zoomCode)->toBe(300)->and($e->getMessage())->toContain('basic plan type');
    }

    expect(fn () => $this->fake->updateUserLicense($this->connection, 'u60_bundleworkplace2', LicenseChange::zoomOne(4)))->toThrow(ZoomApiException::class, 'Invalid parameter: zoom_one_type.');
});

test('features, bundles and upcoming meetings come from fixtures', function () {
    expect($this->fake->userFeatures($this->connection, 'u62_phone1')->zoomPhone)->toBeTrue()
        ->and($this->fake->userFeatures($this->connection, 'u62_phone1')->hasAnyAddOn())->toBeTrue()
        ->and($this->fake->userFeatures($this->connection, 'u04_teacher1')->hasAnyAddOn())->toBeFalse()
        ->and($this->fake->getUser($this->connection, 'u59_bundleworkplace1')->hasBundle())->toBeTrue()
        ->and($this->fake->getUser($this->connection, 'u61_bundleunited')->hasBundle())->toBeTrue()
        ->and($this->fake->getUser($this->connection, 'u04_teacher1')->hasBundle())->toBeFalse()
        ->and($this->fake->upcomingMeetings($this->connection, 'u66_upcoming1'))->toHaveCount(1)
        ->and($this->fake->upcomingMeetings($this->connection, 'u04_teacher1'))->toBe([]);
});
