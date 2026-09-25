<?php

use App\Enums\Bucket;
use App\Jobs\ScanOrganization;
use App\Jobs\SyncZoomMemberFromWebhook;
use App\Models\Exclusion;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Models\ZoomSeatsSnapshot;
use App\Scan\Classifier;
use App\Tenancy\Tenancy;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

function connectedOrganization(array $settings = [], array $connection = []): Organization
{
    $organization = Organization::factory()->withMember(User::factory()->create())->create(['settings' => $settings]);
    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create($connection));

    return $organization;
}

function runScan(Organization $organization, string $trigger = Scan::TRIGGER_MANUAL): Scan
{
    $scan = ScanOrganization::start($organization, $trigger);

    return app(Tenancy::class)->runAs($organization, fn () => Scan::query()->findOrFail($scan->id));
}

test('a full scan of the fixture account produces the expected buckets, seats and totals', function () {
    $organization = connectedOrganization();

    $scan = runScan($organization);

    expect($scan->status)->toBe(Scan::STATUS_DONE)
        ->and($scan->error)->toBeNull()
        ->and($scan->threshold_days)->toBe(90);

    app(Tenancy::class)->runAs($organization, function () use ($scan) {
        $byBucket = ZoomMember::query()->present()->get()->groupBy(fn (ZoomMember $m) => $m->bucket->value)->map->count()->sortKeys()->all();

        expect($byBucket)->toEqual([
            Bucket::Healthy->value => 37,
            Bucket::IdleLicensed->value => 11,
            Bucket::DeactivatedLicensed->value => 5,
            Bucket::PendingLicensed->value => 4,
            Bucket::Protected->value => 12,
        ]);

        $reasons = fn (string $id) => ZoomMember::query()->where('zoom_user_id', $id)->firstOrFail()->protected_reasons;
        expect($reasons('u03_leeadmin'))->toBe([Classifier::REASON_ROLE])
            ->and($reasons('u59_bundleworkplace1'))->toBe([Classifier::REASON_BUNDLE])
            ->and($reasons('u61_bundleunited'))->toBe([Classifier::REASON_BUNDLE])
            ->and($reasons('u62_phone1'))->toBe(['has Zoom Phone'])
            ->and($reasons('u64_webinar1'))->toBe(['has a Webinar add-on'])
            ->and($reasons('u66_upcoming1'))->toBe([Classifier::REASON_UPCOMING])
            ->and($reasons('u68_newhire1'))->toBe(['account created less than 30 days ago']);

        $pendingRecent = ZoomMember::query()->where('email', 'invite1@example.edu')->firstOrFail();
        expect($pendingRecent->zoom_user_id)->toBeNull()
            ->and($pendingRecent->bucket)->toBe(Bucket::PendingLicensed)
            ->and($pendingRecent->eligible_for_downgrade)->toBeFalse()
            // The account has Workplace bundle seats and pending rows carry no bundle fields, so bundle status is unknown.
            ->and($pendingRecent->protected_reasons)->toBe([Classifier::REASON_BUNDLE_UNKNOWN, 'invited less than 30 days ago']);

        $idle = ZoomMember::query()->where('zoom_user_id', 'u32_never1')->firstOrFail();
        expect($idle->last_hosted_window)->toBe('none')
            ->and($idle->eligible_for_downgrade)->toBeTrue()
            ->and($idle->upcoming_meetings_count)->toBe(0)
            ->and($idle->bundle_known)->toBeTrue();

        $healthy = ZoomMember::query()->where('zoom_user_id', 'u22_idle301')->firstOrFail();
        expect($healthy->last_hosted_window)->toBe('30-60')->and($healthy->bucket)->toBe(Bucket::Healthy);

        $deactivated = ZoomMember::query()->where('zoom_user_id', 'u38_gone1')->firstOrFail();
        expect($deactivated->status)->toBe('inactive')->and($deactivated->eligible_for_downgrade)->toBeTrue()->and($deactivated->last_login_at)->toBeNull();

        $snapshot = ZoomSeatsSnapshot::query()->where('scan_id', $scan->id)->firstOrFail();
        expect($snapshot->source)->toBe('plan_usage')
            ->and($snapshot->unassigned_seats)->toBe(5)
            ->and($snapshot->purchased_seats)->toBe($snapshot->used_seats + 5);

        expect($scan->total('buckets.idle_licensed.count'))->toBe(11)
            ->and($scan->total('unassigned.count'))->toBe(5)
            ->and($scan->total('reclaimable_seats'))->toBe(5 + 5 + 4 + 11)
            ->and($scan->total('waste_annual_cents'))->toBe(25 * 14900)
            ->and($scan->total('eligible_for_downgrade'))->toBe(5 + 11)
            ->and($scan->total('members_total'))->toBe(69);

        // Only candidates got the extra per-user calls: 11 idle + 12 idle-but-protected + 5 deactivated = 28; never healthy hosts or pending invites.
        $fake = app(FakeZoomClient::class);
        expect(count($fake->callsTo('getUser')))->toBe(28)
            ->and(count($fake->callsTo('upcomingMeetings')))->toBe(23)
            ->and(count($fake->callsTo('hostReport')))->toBe(3)
            ->and(count($fake->callsTo('listUsers')))->toBe(3);
    });

    expect($organization->fresh()->setting('scan.last_completed_at'))->not->toBeNull();
});

test('the threshold changes who is idle and how far back reports go', function () {
    $organization = connectedOrganization(['scan' => ['threshold_days' => 30]]);
    $scan = runScan($organization);

    app(Tenancy::class)->runAs($organization, function () use ($scan) {
        expect($scan->total('buckets.idle_licensed.count'))->toBe(18);
    });
    expect(count(app(FakeZoomClient::class)->callsTo('hostReport')))->toBe(3);

    $organization = connectedOrganization(['scan' => ['threshold_days' => 180]]);
    $before = count(app(FakeZoomClient::class)->callsTo('hostReport'));
    $scan = runScan($organization);
    expect(count(app(FakeZoomClient::class)->callsTo('hostReport')) - $before)->toBe(6);
    app(Tenancy::class)->runAs($organization, function () use ($scan) {
        // idle90 users hosted 120-160 days ago: within 180 → healthy. never-hosted 6 + rooms 2 remain idle.
        expect($scan->total('buckets.idle_licensed.count'))->toBe(8);
    });
});

test('exclusions protect matching members', function () {
    $organization = connectedOrganization();
    app(Tenancy::class)->runAs($organization, function () {
        Exclusion::query()->create(['type' => Exclusion::TYPE_EMAIL, 'value' => 'never1@example.edu', 'reason' => 'board member']);
        Exclusion::query()->create(['type' => Exclusion::TYPE_DOMAIN, 'value' => 'example.edu', 'reason' => 'expired', 'expires_at' => now()->subDay()]);
    });

    runScan($organization);

    app(Tenancy::class)->runAs($organization, function () {
        $m = ZoomMember::query()->where('zoom_user_id', 'u32_never1')->firstOrFail();
        expect($m->bucket)->toBe(Bucket::Protected)->and($m->protected_reasons)->toBe(['excluded: email never1@example.edu (board member)']);
        expect(ZoomMember::query()->where('zoom_user_id', 'u33_never2')->firstOrFail()->bucket)->toBe(Bucket::IdleLicensed);
    });
});

test('a second scan re-keys activated invites and marks vanished users removed', function () {
    $organization = connectedOrganization();
    runScan($organization);

    $fake = app(FakeZoomClient::class);
    $fake->patchUser('invite2@example.edu', ['status' => 'active', 'user_created_at' => '@days_ago:400']);
    $users = array_values(array_filter(json_decode(file_get_contents(base_path('tests/Fixtures/zoom/users.json')), true), fn ($u) => $u['email'] !== 'never6@example.edu'));
    $fake->withUsers($users)->patchUser('invite2@example.edu', ['status' => 'active', 'user_created_at' => '@days_ago:400']);

    runScan($organization);

    app(Tenancy::class)->runAs($organization, function () {
        expect(ZoomMember::query()->where('email', 'invite2@example.edu')->count())->toBe(1)
            ->and(ZoomMember::query()->where('email', 'invite2@example.edu')->firstOrFail()->zoom_user_id)->toBe('u46_invite2')
            ->and(ZoomMember::query()->where('email', 'never6@example.edu')->firstOrFail()->removed_at)->not->toBeNull()
            ->and(ZoomMember::query()->present()->count())->toBe(68);
    });
});

test('missing plan usage falls back to the user summary and warns', function () {
    $organization = connectedOrganization();
    app(FakeZoomClient::class)->withPlanUsageUnavailable();

    $scan = runScan($organization);

    app(Tenancy::class)->runAs($organization, function () use ($scan) {
        $snapshot = ZoomSeatsSnapshot::query()->where('scan_id', $scan->id)->firstOrFail();
        expect($snapshot->source)->toBe('user_summary')->and($snapshot->purchased_seats)->toBeNull()->and($snapshot->used_seats)->toBeGreaterThan(0);
        expect($scan->total('unassigned.known'))->toBeFalse()->and($scan->total('reclaimable_seats'))->toBe(20);
        expect(collect($scan->warnings)->pluck('code'))->toContain('plan_usage_unavailable');
        // Unknown plan mix → bundles must be confirmed per user; the fake exposes bundle fields, so idle counts hold.
        expect($scan->total('buckets.idle_licensed.count'))->toBe(11);
    });
});

test('a failing host report makes hosting unknown and warns instead of marking users idle', function () {
    $organization = connectedOrganization();
    app(FakeZoomClient::class)->failNext('hostReport', new ZoomApiException('No permission.', 400, 200));

    $scan = runScan($organization);

    app(Tenancy::class)->runAs($organization, function () use ($scan) {
        expect($scan->status)->toBe(Scan::STATUS_DONE)
            ->and(collect($scan->warnings)->pluck('code'))->toContain('report_failed')
            ->and($scan->total('buckets.idle_licensed.count'))->toBe(0)
            ->and($scan->total('buckets.deactivated_licensed.count'))->toBe(5)
            ->and(ZoomMember::query()->where('zoom_user_id', 'u32_never1')->firstOrFail()->last_hosted_window)->toBe('unknown');
    });
});

test('a partial user list is flagged as a data-quality warning', function () {
    $organization = connectedOrganization();
    app(FakeZoomClient::class)->withPartialUserList();

    $scan = runScan($organization);

    expect(collect($scan->warnings)->pluck('code'))->toContain('pagination_mismatch');
});

test('missing scopes are reported and the affected checks are skipped safely', function () {
    $organization = connectedOrganization(connection: ['scopes' => ['user:read:list_users:admin', 'user:read:user:admin']]);

    $scan = runScan($organization);

    $codes = collect($scan->warnings)->pluck('code');
    expect($scan->status)->toBe(Scan::STATUS_DONE)
        ->and($codes->filter(fn ($c) => $c === 'missing_scope')->count())->toBe(2)
        ->and($scan->total('buckets.idle_licensed.count'))->toBe(0);
});

test('scans fail cleanly without a connection or when the active list cannot be read', function () {
    $organization = Organization::factory()->withMember(User::factory()->create())->create();
    $scan = runScan($organization);
    expect($scan->status)->toBe(Scan::STATUS_FAILED)->and($scan->error)->toContain('not connected');

    $organization = connectedOrganization();
    app(FakeZoomClient::class)->failNext('listUsers', new ZoomApiException('Invalid access token.', 401, 124));
    $scan = runScan($organization);
    expect($scan->status)->toBe(Scan::STATUS_FAILED)->and($scan->error)->toContain('Zoom error 124');
});

test('start creates a queued scan row and dispatches the job once', function () {
    Queue::fake();
    $organization = connectedOrganization();

    $scan = ScanOrganization::start($organization, Scan::TRIGGER_ONBOARDING);

    expect($scan->status)->toBe(Scan::STATUS_QUEUED)->and($scan->trigger)->toBe('onboarding');
    Queue::assertPushed(ScanOrganization::class, fn ($job) => $job->scanId === $scan->id && $job->organizationId === $organization->id);
});

test('the scheduler starts one scan per organization at 03:00 local time', function () {
    Queue::fake();
    $chicago = connectedOrganization();
    $chicago->update(['timezone' => 'America/Chicago']);
    $tokyo = connectedOrganization();
    $tokyo->update(['timezone' => 'Asia/Tokyo']);
    $disconnected = Organization::factory()->withMember(User::factory()->create())->create(['timezone' => 'America/Chicago']);

    $this->travelTo(CarbonImmutable::parse('2026-09-25 03:30', 'America/Chicago'));
    $this->artisan('seattrim:dispatch-scheduled-scans')->assertSuccessful();
    Queue::assertPushed(ScanOrganization::class, 1);
    Queue::assertPushed(ScanOrganization::class, fn ($job) => $job->organizationId === $chicago->id);

    // Same hour again: no duplicate.
    $this->travelTo(CarbonImmutable::parse('2026-09-25 03:50', 'America/Chicago'));
    $this->artisan('seattrim:dispatch-scheduled-scans')->assertSuccessful();
    Queue::assertPushed(ScanOrganization::class, 1);

    $this->travelTo(CarbonImmutable::parse('2026-09-26 03:10', 'Asia/Tokyo'));
    $this->artisan('seattrim:dispatch-scheduled-scans')->assertSuccessful();
    Queue::assertPushed(ScanOrganization::class, 2);
    expect($disconnected->fresh()->setting('scan.last_scheduled_date'))->toBeNull();
});

test('a user webhook refreshes the member and drops a now-basic user out of the waste buckets', function () {
    $organization = connectedOrganization();
    runScan($organization);
    $connection = app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::query()->firstOrFail());

    app(FakeZoomClient::class)->updateUserType($connection, 'u32_never1', ZoomUser::TYPE_BASIC);
    (new SyncZoomMemberFromWebhook($organization->id, 'user.updated', ['id' => 'u32_never1']))->handle(app(Tenancy::class), app(FakeZoomClient::class));

    app(Tenancy::class)->runAs($organization, function () {
        $m = ZoomMember::query()->where('zoom_user_id', 'u32_never1')->firstOrFail();
        expect($m->type)->toBe(ZoomUser::TYPE_BASIC)->and($m->bucket)->toBe(Bucket::Healthy);
    });

    (new SyncZoomMemberFromWebhook($organization->id, 'user.deleted', ['id' => 'u33_never2']))->handle(app(Tenancy::class), app(FakeZoomClient::class));
    app(Tenancy::class)->runAs($organization, function () {
        expect(ZoomMember::query()->where('zoom_user_id', 'u33_never2')->firstOrFail()->removed_at)->not->toBeNull();
    });
});
