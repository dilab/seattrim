<?php

use App\Enums\Bucket;
use App\Scan\Classifier;
use App\Scan\MemberFacts;
use App\Scan\ScanSettings;
use App\Scan\Window;
use App\Zoom\Data\ZoomUser;
use Carbon\CarbonImmutable;

$now = CarbonImmutable::parse('2026-09-25 12:00:00');

function facts(array $overrides = []): MemberFacts
{
    $defaults = [
        'status' => ZoomUser::STATUS_ACTIVE,
        'type' => ZoomUser::TYPE_LICENSED,
        'isOwnerOrAdmin' => false,
        'isRoom' => false,
        'hasBundle' => false,
        'bundleKnown' => true,
        'addOns' => [],
        'upcomingMeetings' => 0,
        'createdAt' => CarbonImmutable::parse('2025-01-01'),
        'lastLoginAt' => null,
        'exclusionReason' => null,
        'excludedUntil' => null,
        'hostingKnown' => true,
        'meetingsByWindow' => ['0-30' => 0, '30-60' => 0, '60-90' => 0],
    ];

    return new MemberFacts(...array_merge($defaults, $overrides));
}

function classifier(int $threshold = 90, int $newHire = 30): Classifier
{
    return new Classifier(new ScanSettings($threshold, $newHire));
}

test('non-licensed members are always healthy and never eligible', function (int $type, string $status) use ($now) {
    $c = classifier()->classify(facts(['type' => $type, 'status' => $status, 'isRoom' => true, 'hasBundle' => true]), $now);

    expect($c->bucket)->toBe(Bucket::Healthy)
        ->and($c->eligibleForDowngrade)->toBeFalse()
        ->and($c->protectedReasons)->toBe([]);
})->with([
    [ZoomUser::TYPE_BASIC, ZoomUser::STATUS_ACTIVE],
    [ZoomUser::TYPE_BASIC, ZoomUser::STATUS_INACTIVE],
    [ZoomUser::TYPE_BASIC, ZoomUser::STATUS_PENDING],
    [ZoomUser::TYPE_UNASSIGNED, ZoomUser::STATUS_ACTIVE],
    [99, ZoomUser::STATUS_ACTIVE],
]);

test('last hosted window is the most recent window with a meeting', function () {
    $c = classifier();

    expect($c->lastHostedWindow(facts(['meetingsByWindow' => ['0-30' => 0, '30-60' => 2, '60-90' => 5]])))->toBe('30-60')
        ->and($c->lastHostedWindow(facts(['meetingsByWindow' => ['60-90' => 1, '0-30' => 0, '30-60' => 0]])))->toBe('60-90')
        ->and($c->lastHostedWindow(facts(['meetingsByWindow' => ['0-30' => 1]])))->toBe('0-30')
        ->and($c->lastHostedWindow(facts()))->toBe(Window::NONE)
        ->and($c->lastHostedWindow(facts(['hostingKnown' => false])))->toBe(Window::UNKNOWN);
});

test('idleness depends on the threshold', function (int $threshold, array $windows, bool $idle) {
    expect(classifier($threshold)->isIdle(facts(['meetingsByWindow' => $windows])))->toBe($idle);
})->with([
    'hosted this month, threshold 30' => [30, ['0-30' => 1], false],
    'hosted 30-60 days ago, threshold 30' => [30, ['0-30' => 0, '30-60' => 1], true],
    'hosted 30-60 days ago, threshold 60' => [60, ['0-30' => 0, '30-60' => 1], false],
    'hosted 60-90 days ago, threshold 60' => [60, ['0-30' => 0, '30-60' => 0, '60-90' => 1], true],
    'hosted 60-90 days ago, threshold 90' => [90, ['0-30' => 0, '30-60' => 0, '60-90' => 1], false],
    'hosted 90-120 days ago, threshold 90' => [90, ['0-30' => 0, '30-60' => 0, '60-90' => 0, '90-120' => 1], true],
    'hosted 150-180 days ago, threshold 180' => [180, ['0-30' => 0, '150-180' => 1], false],
    'never hosted, threshold 180' => [180, ['0-30' => 0, '150-180' => 0], true],
    'never hosted, threshold 30' => [30, [], true],
]);

test('unknown hosting data never marks a user idle', function () use ($now) {
    $c = classifier()->classify(facts(['hostingKnown' => false]), $now);

    expect($c->bucket)->toBe(Bucket::Healthy)
        ->and($c->lastHostedWindow)->toBe(Window::UNKNOWN)
        ->and($c->idle)->toBeFalse();
});

test('an idle licensed user with no guardrail is idle_licensed and eligible', function () use ($now) {
    $c = classifier()->classify(facts(), $now);

    expect($c->bucket)->toBe(Bucket::IdleLicensed)
        ->and($c->eligibleForDowngrade)->toBeTrue()
        ->and($c->protectedReasons)->toBe([])
        ->and($c->lastHostedWindow)->toBe(Window::NONE);
});

test('a hosting licensed user is healthy even when guardrails apply', function () use ($now) {
    $c = classifier()->classify(facts(['meetingsByWindow' => ['0-30' => 4], 'hasBundle' => true]), $now);

    expect($c->bucket)->toBe(Bucket::Healthy)
        ->and($c->eligibleForDowngrade)->toBeFalse()
        ->and($c->protectedReasons)->toBe([Classifier::REASON_BUNDLE]);
});

test('each guardrail protects an idle user with a readable reason', function (array $overrides, string $reason) use ($now) {
    $c = classifier()->classify(facts($overrides), $now);

    expect($c->bucket)->toBe(Bucket::Protected)
        ->and($c->eligibleForDowngrade)->toBeFalse()
        ->and($c->protectedReasons)->toContain($reason);
})->with([
    'bundle' => [['hasBundle' => true], Classifier::REASON_BUNDLE],
    'bundle unknown' => [['bundleKnown' => false], Classifier::REASON_BUNDLE_UNKNOWN],
    'phone' => [['addOns' => ['zoom_phone']], 'has Zoom Phone'],
    'webinar' => [['addOns' => ['webinar']], 'has a Webinar add-on'],
    'large meeting' => [['addOns' => ['large_meeting']], 'has a Large Meeting add-on'],
    'events' => [['addOns' => ['zoom_events']], 'has a Zoom Events add-on'],
    'other add-on' => [['addOns' => ['zoom_whiteboard_plus']], 'has add-on whiteboard plus'],
    'upcoming meetings' => [['upcomingMeetings' => 2], Classifier::REASON_UPCOMING],
    'upcoming unknown' => [['upcomingMeetings' => null], Classifier::REASON_UPCOMING_UNKNOWN],
    'owner or admin' => [['isOwnerOrAdmin' => true], Classifier::REASON_ROLE],
    'room' => [['isRoom' => true], Classifier::REASON_ROOM],
    'exclusion' => [['exclusionReason' => 'domain board.example.edu'], 'excluded: domain board.example.edu'],
    'kept by user' => [['excludedUntil' => CarbonImmutable::parse('2026-12-01')], 'kept by the user until 2026-12-01'],
    'new hire' => [['createdAt' => CarbonImmutable::parse('2026-09-10')], 'account created less than 30 days ago'],
]);

test('an expired keep-my-license window no longer protects', function () use ($now) {
    $c = classifier()->classify(facts(['excludedUntil' => CarbonImmutable::parse('2026-09-01')]), $now);

    expect($c->bucket)->toBe(Bucket::IdleLicensed);
});

test('the new-hire rule uses the configured days and can be disabled', function () use ($now) {
    $created = CarbonImmutable::parse('2026-09-15'); // 10 days before now

    expect(classifier(90, 30)->classify(facts(['createdAt' => $created]), $now)->bucket)->toBe(Bucket::Protected)
        ->and(classifier(90, 7)->classify(facts(['createdAt' => $created]), $now)->bucket)->toBe(Bucket::IdleLicensed)
        ->and(classifier(90, 0)->classify(facts(['createdAt' => $created]), $now)->bucket)->toBe(Bucket::IdleLicensed);
});

test('multiple guardrails are all listed once, in a stable order', function () use ($now) {
    $c = classifier()->classify(facts(['isOwnerOrAdmin' => true, 'hasBundle' => true, 'addOns' => ['zoom_phone', 'zoom_phone'], 'upcomingMeetings' => 1]), $now);

    expect($c->protectedReasons)->toBe([Classifier::REASON_ROLE, Classifier::REASON_BUNDLE, 'has Zoom Phone', Classifier::REASON_UPCOMING]);
});

test('deactivated licensed users are bucketed regardless of hosting, and skip host-only guardrails', function () use ($now) {
    $c = classifier()->classify(facts(['status' => ZoomUser::STATUS_INACTIVE, 'meetingsByWindow' => ['0-30' => 5], 'upcomingMeetings' => null, 'createdAt' => CarbonImmutable::parse('2026-09-20')]), $now);

    expect($c->bucket)->toBe(Bucket::DeactivatedLicensed)
        ->and($c->eligibleForDowngrade)->toBeTrue()
        ->and($c->protectedReasons)->toBe([]);

    $protectedDeactivated = classifier()->classify(facts(['status' => ZoomUser::STATUS_INACTIVE, 'hasBundle' => true]), $now);

    expect($protectedDeactivated->bucket)->toBe(Bucket::DeactivatedLicensed)
        ->and($protectedDeactivated->eligibleForDowngrade)->toBeFalse()
        ->and($protectedDeactivated->protectedReasons)->toBe([Classifier::REASON_BUNDLE]);
});

test('pending licensed invites are bucketed, recent invites are protected, upcoming meetings are not required', function () use ($now) {
    $old = classifier()->classify(facts(['status' => ZoomUser::STATUS_PENDING, 'upcomingMeetings' => null, 'createdAt' => CarbonImmutable::parse('2026-06-01')]), $now);
    expect($old->bucket)->toBe(Bucket::PendingLicensed)->and($old->eligibleForDowngrade)->toBeTrue();

    $recent = classifier()->classify(facts(['status' => ZoomUser::STATUS_PENDING, 'upcomingMeetings' => null, 'createdAt' => CarbonImmutable::parse('2026-09-20')]), $now);
    expect($recent->bucket)->toBe(Bucket::PendingLicensed)
        ->and($recent->eligibleForDowngrade)->toBeFalse()
        ->and($recent->protectedReasons)->toBe(['invited less than 30 days ago']);
});

test('windows cover the lookback in ≤31-day slices ending today', function () use ($now) {
    $windows = Window::covering(90, $now);

    expect($windows)->toHaveCount(3)
        ->and($windows[0]->label())->toBe('0-30')
        ->and($windows[2]->label())->toBe('60-90')
        ->and($windows[0]->to->toDateString())->toBe('2026-09-25')
        ->and($windows[0]->from->toDateString())->toBe('2026-08-27')
        ->and($windows[1]->to->toDateString())->toBe('2026-08-26')
        ->and($windows[1]->from->toDateString())->toBe('2026-07-28');

    foreach ($windows as $w) {
        expect($w->from->diffInDays($w->to))->toBeLessThanOrEqual(31);
    }

    expect(Window::covering(180, $now))->toHaveCount(6)
        ->and(Window::covering(30, $now))->toHaveCount(1)
        ->and(Window::startOf('90-120'))->toBe(90)
        ->and(Window::startOf(Window::NONE))->toBeNull();
});

test('scan settings clamp the lookback and validate the threshold', function () {
    expect((new ScanSettings(30))->lookbackDays())->toBe(90)
        ->and((new ScanSettings(180))->lookbackDays())->toBe(180)
        ->and((new ScanSettings(90))->lookbackDays())->toBe(90);
});
