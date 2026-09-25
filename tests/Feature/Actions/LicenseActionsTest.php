<?php

use App\Actions\License\QueueLicenseActions;
use App\Actions\License\SafetyCap;
use App\Enums\Bucket;
use App\Enums\Role;
use App\Jobs\DowngradeMember;
use App\Jobs\ScanOrganization;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Tenancy\Tenancy;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->user)->create();
    $this->user->switchToOrganization($this->organization);
    onPaidPlan($this->organization);
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
    $this->fake = app(FakeZoomClient::class);
});

function member(Organization $organization, string $zoomId): ZoomMember
{
    return app(Tenancy::class)->runAs($organization, fn () => ZoomMember::query()->where('zoom_user_id', $zoomId)->firstOrFail());
}

function queueDowngrade(Organization $organization, ZoomMember $member, User $user, bool $dryRun = false): LicenseAction
{
    return app(Tenancy::class)->runAs($organization, fn () => app(QueueLicenseActions::class)->downgrade($organization, collect([$member]), LicenseAction::SOURCE_MANUAL, $user, $dryRun)['actions']->first());
}

test('a manual downgrade re-checks, updates Zoom, confirms and logs with the tracking id', function () {
    $member = member($this->organization, 'u32_never1');
    expect($member->eligible_for_downgrade)->toBeTrue();

    $action = queueDowngrade($this->organization, $member, $this->user);

    $action->refresh();
    expect($action->status)->toBe('done')
        ->and($action->zoom_tracking_id)->toStartWith('fake-')
        ->and($action->from_type)->toBe(2)->and($action->to_type)->toBe(1)
        ->and($action->performed_by)->toBe($this->user->id)
        ->and($action->source)->toBe('manual')
        ->and($action->performed_at)->not->toBeNull();

    $member->refresh();
    expect($member->type)->toBe(ZoomUser::TYPE_BASIC)
        ->and($member->bucket)->toBe(Bucket::Healthy)
        ->and($member->eligible_for_downgrade)->toBeFalse();

    // Fresh data was used: re-fetch, features, upcoming, patch, confirm.
    $methods = array_map(fn ($c) => $c['method'], array_slice($this->fake->calls, -5));
    expect($methods)->toBe(['getUser', 'userFeatures', 'upcomingMeetings', 'updateUserType', 'getUser']);
});

test('a downgrade is skipped when a guardrail appears on fresh data', function () {
    $member = member($this->organization, 'u32_never1');
    $this->fake->patchUser('u32_never1', ['role_id' => '1', 'role_name' => 'Admin']);

    $action = queueDowngrade($this->organization, $member, $this->user);

    expect($action->fresh()->status)->toBe('skipped')
        ->and($action->fresh()->reason)->toContain('Guardrail: Zoom account owner or admin')
        ->and(empty($this->fake->callsTo('updateUserType')))->toBeTrue()
        ->and($member->fresh()->type)->toBe(ZoomUser::TYPE_LICENSED);
});

test('a downgrade is skipped when the user hosted a meeting since the scan or is already Basic', function () {
    $member = member($this->organization, 'u32_never1');
    $this->fake->patchUser('u32_never1', ['type' => 1]);
    $action = queueDowngrade($this->organization, $member, $this->user);
    expect($action->fresh()->status)->toBe('skipped')->and($action->fresh()->reason)->toContain('no longer Licensed');
    expect($member->fresh()->type)->toBe(1);
});

test('a vanished user marks the member removed and skips', function () {
    $member = member($this->organization, 'u33_never2');
    $this->fake->failNext('getUser', new ZoomApiException('User does not exist: u33_never2', 404, 1001));

    $action = queueDowngrade($this->organization, $member, $this->user);

    expect($action->fresh()->status)->toBe('skipped')
        ->and($action->fresh()->reason)->toContain('no longer exists')
        ->and($member->fresh()->removed_at)->not->toBeNull();
});

test('a Zoom Room is flagged and the action fails with the Zoom error', function () {
    $room = member($this->organization, 'u57_roomlibrary');
    expect($room->eligible_for_downgrade)->toBeTrue();

    $action = queueDowngrade($this->organization, $room, $this->user);

    expect($action->fresh()->status)->toBe('failed')
        ->and($action->fresh()->reason)->toBe('Zoom error 200: A Zoom Room user cannot be changed to a free user type: u57_roomlibrary')
        ->and($action->fresh()->zoom_tracking_id)->toStartWith('fake-');

    $room->refresh();
    expect($room->is_room)->toBeTrue()->and($room->bucket)->toBe(Bucket::Protected)->and($room->protected_reasons)->toContain('Zoom Room');
});

test('unexpected Zoom errors are stored verbatim', function () {
    $member = member($this->organization, 'u34_never3');
    $this->fake->failNext('updateUserType', new ZoomApiException('You cannot change the user type to "Basic" because this user has an upcoming Zoom Events scheduled.', 400, 300, 'trk-x'));

    $action = queueDowngrade($this->organization, $member, $this->user);

    expect($action->fresh()->status)->toBe('failed')
        ->and($action->fresh()->reason)->toBe('Zoom error 300: You cannot change the user type to "Basic" because this user has an upcoming Zoom Events scheduled.')
        ->and($action->fresh()->zoom_tracking_id)->toBe('trk-x');
});

test('a dry run logs what would have happened and changes nothing', function () {
    $member = member($this->organization, 'u35_never4');

    $action = queueDowngrade($this->organization, $member, $this->user, dryRun: true);

    expect($action->fresh()->status)->toBe('skipped')
        ->and($action->fresh()->dry_run)->toBeTrue()
        ->and($action->fresh()->reason)->toContain('Dry run')
        ->and(empty($this->fake->callsTo('updateUserType')))->toBeTrue();
});

test('deactivated licensed users can be downgraded and pending invites are addressed by email', function () {
    $gone = member($this->organization, 'u38_gone1');
    $action = queueDowngrade($this->organization, $gone, $this->user);
    expect($action->fresh()->status)->toBe('done')->and($gone->fresh()->type)->toBe(1);

    $pending = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->where('email', 'invite2@example.edu')->firstOrFail());
    expect($pending->zoom_user_id)->toBeNull();
    // The fixture account has bundle plans, so the invite is protected; force eligibility to exercise the email path.
    $pending->forceFill(['eligible_for_downgrade' => true, 'bundle_known' => true])->save();
    $action = queueDowngrade($this->organization, $pending, $this->user);
    expect($action->fresh()->status)->toBe('done')->and($pending->fresh()->type)->toBe(1);
    expect(collect($this->fake->callsTo('updateUserType'))->last()['args'][0])->toBe('invite2@example.edu');
});

test('restore puts a downgraded user back and fails clearly when Zoom has no free seat', function () {
    $member = member($this->organization, 'u32_never1');
    queueDowngrade($this->organization, $member, $this->user);
    expect($member->fresh()->type)->toBe(1);

    $restore = app(Tenancy::class)->runAs($this->organization, fn () => app(QueueLicenseActions::class)->restore($this->organization, collect([$member->fresh()]), LicenseAction::SOURCE_MANUAL, $this->user)['actions']->first());
    expect($restore->fresh()->status)->toBe('done')->and($member->fresh()->type)->toBe(2);

    $other = member($this->organization, 'u33_never2');
    queueDowngrade($this->organization, $other, $this->user);
    $this->fake->failNext('updateUserType', new ZoomApiException('Your request to convert this user\'s plan type to "Licensed" wasn\'t approved because your account has reached the permitted maximum number of "50" users.', 400, 2034));
    $restore = app(Tenancy::class)->runAs($this->organization, fn () => app(QueueLicenseActions::class)->restore($this->organization, collect([$other->fresh()]), LicenseAction::SOURCE_MANUAL, $this->user)['actions']->first());
    expect($restore->fresh()->status)->toBe('failed')->and($restore->fresh()->reason)->toStartWith('No free Licensed seat: Zoom error 2034');
});

test('bulk actions create one batch with one job and audit row per member', function () {
    Bus::fake();
    $members = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->where('eligible_for_downgrade', true)->limit(3)->get());

    $result = app(Tenancy::class)->runAs($this->organization, fn () => app(QueueLicenseActions::class)->downgrade($this->organization, $members, LicenseAction::SOURCE_BULK, $this->user));

    expect($result['actions'])->toHaveCount(3)
        ->and($result['actions']->pluck('batch_id')->unique())->toHaveCount(1)
        ->and($result['actions']->first()->status)->toBe('queued');
    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3 && $batch->jobs->every(fn ($j) => $j instanceof DowngradeMember));
});

test('the safety cap is max(10, 10% of licensed seats)', function () {
    app(Tenancy::class)->runAs($this->organization, function () {
        expect(SafetyCap::limit())->toBe(10)->and(SafetyCap::exceeds(10))->toBeFalse()->and(SafetyCap::exceeds(11))->toBeTrue();

        Scan::query()->latest('id')->firstOrFail()->forceFill(['totals' => ['licensed_total' => 450]])->save();
        expect(SafetyCap::limit())->toBe(45);
    });
});

test('the member drawer offers downgrade to admins, and the bulk bar enforces the cap acknowledgement', function () {
    $member = member($this->organization, 'u32_never1');

    actingAsMemberOf($this->organization, $this->user)->test('pages::members.actions', ['member' => $member])
        ->assertSee('Downgrade to Basic')
        ->call('downgrade')
        ->assertHasNoErrors();
    expect($member->fresh()->type)->toBe(1);

    actingAsMemberOf($this->organization, $this->user)->test('pages::members.actions', ['member' => $member->fresh()])
        ->assertSee('Restore Licensed')
        ->call('restore');
    expect($member->fresh()->type)->toBe(2);

    $viewer = User::factory()->create();
    $this->organization->addMember($viewer, Role::Viewer);
    actingAsMemberOf($this->organization, $viewer)->test('pages::members.actions', ['member' => $member->fresh()])
        ->assertDontSee('Downgrade to Basic')
        ->call('downgrade')
        ->assertForbidden();

    $eligible = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->where('eligible_for_downgrade', true)->pluck('id')->map(fn ($i) => (int) $i)->all());
    expect(count($eligible))->toBeGreaterThan(10);

    $bar = actingAsMemberOf($this->organization, $this->user)->test('pages::members.bulk-bar', ['selected' => $eligible]);
    $bar->call('open', 'downgrade')->assertSee('Safety cap');
    $bar->call('run')->assertHasErrors(['acknowledgeCap']);
    expect(LicenseAction::query()->allOrganizations()->count())->toBe(2);

    Bus::fake();
    $bar->set('acknowledgeCap', true)->call('run')->assertHasNoErrors();
    expect(LicenseAction::query()->allOrganizations()->where('source', 'bulk')->count())->toBe(count($eligible));
});

test('the audit log lists, filters and exports actions', function () {
    $member = member($this->organization, 'u32_never1');
    queueDowngrade($this->organization, $member, $this->user);
    $room = member($this->organization, 'u57_roomlibrary');
    queueDowngrade($this->organization, $room, $this->user);

    $this->actingAs($this->user)->get(route('audit'))->assertOk()->assertSee('never1@example.edu')->assertSee('room.library@example.edu')->assertSee('Zoom error 200');

    $component = actingAsMemberOf($this->organization, $this->user)->test('pages::audit')->set('status', 'failed');
    expect($component->get('actions')->total())->toBe(1);

    $response = $this->actingAs($this->user)->get(route('audit.export', ['status' => 'done']));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('never1@example.edu')->not->toContain('room.library@example.edu')->toContain('member_email');

    // Another organization sees nothing.
    $stranger = User::factory()->create();
    $strangerOrg = Organization::factory()->withMember($stranger)->create();
    $stranger->switchToOrganization($strangerOrg);
    $this->actingAs($stranger)->get(route('audit'))->assertOk()->assertDontSee('never1@example.edu');
});
