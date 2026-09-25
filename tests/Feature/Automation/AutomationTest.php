<?php

use App\Automation\AutomationRunner;
use App\Automation\AutomationSettings;
use App\Automation\DigestBuilder;
use App\Enums\Bucket;
use App\Enums\Role;
use App\Jobs\RunAutomation;
use App\Jobs\ScanOrganization;
use App\Models\DowngradeNotice;
use App\Models\Exclusion;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Notifications\DowngradeWarning;
use App\Notifications\LicenseKept;
use App\Notifications\WeeklyDigest;
use App\Scan\ScanRunner;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->user)->create(['timezone' => 'America/Chicago']);
    $this->user->switchToOrganization($this->organization);
    onPaidPlan($this->organization);
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
});

function enableAutomation(Organization $organization, array $overrides = []): void
{
    $organization->setSetting('automation', array_merge((new AutomationSettings(enabled: true, dryRun: true))->toArray(), $overrides));
    $organization->save();
}

function runAutomation(Organization $organization, ?CarbonImmutable $now = null): array
{
    return app(Tenancy::class)->runAs($organization, fn () => app(AutomationRunner::class)->run($organization, $now));
}

test('automation is off by default and does nothing', function () {
    Notification::fake();
    $result = runAutomation($this->organization);

    expect($result)->toBe(['warned' => 0, 'cancelled' => 0, 'executed' => 0, 'deferred' => 0]);
    Notification::assertNothingSent();
});

test('the first run warns eligible idle and pending users with a signed keep link, skipping emails to pending invites', function () {
    Notification::fake();
    enableAutomation($this->organization);

    $result = runAutomation($this->organization);

    // 11 idle + 4 pending in the bucket, but pending invites are not eligible on this account (bundle unknown).
    expect($result['warned'])->toBe(11)->and($result['executed'])->toBe(0);

    $notices = app(Tenancy::class)->runAs($this->organization, fn () => DowngradeNotice::query()->open()->with('member')->get());
    expect($notices)->toHaveCount(11)
        ->and($notices->first()->scheduled_for->toDateString())->toBe(now()->addDays(7)->toDateString());

    Notification::assertSentOnDemand(DowngradeWarning::class, function (DowngradeWarning $n, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $n->toMail($notifiable);
        $url = $n->keepUrl();

        return array_key_exists('never1@example.edu', $notifiable->routes['mail'])
            && str_contains($url, '/keep/'.$n->notice->id)
            && str_contains($url, 'signature=')
            && $mail->actionUrl === $url
            && str_contains($mail->subject, 'Your Zoom license');
    });
    Notification::assertSentOnDemandTimes(DowngradeWarning::class, 11);

    // A second run does not warn the same people twice.
    expect(runAutomation($this->organization)['warned'])->toBe(0);
});

test('warnings are cancelled when the user is no longer eligible, and due notices run as dry run first', function () {
    Notification::fake();
    enableAutomation($this->organization);
    runAutomation($this->organization);

    $member = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->where('zoom_user_id', 'u32_never1')->firstOrFail());
    $member->forceFill(['eligible_for_downgrade' => false, 'bucket' => Bucket::Protected, 'protected_reasons' => ['has Zoom Phone']])->save();

    $result = runAutomation($this->organization, CarbonImmutable::now()->addDays(8));

    expect($result['cancelled'])->toBe(1)->and($result['executed'])->toBe(10)->and($result['deferred'])->toBe(0);

    app(Tenancy::class)->runAs($this->organization, function () use ($member) {
        expect(DowngradeNotice::query()->where('zoom_member_id', $member->id)->firstOrFail()->cancel_reason)->toContain('guardrail');

        $actions = LicenseAction::query()->where('source', 'rule')->get();
        expect($actions)->toHaveCount(10)
            ->and($actions->every(fn ($a) => $a->dry_run && $a->status === 'skipped' && str_starts_with($a->reason, 'Dry run')))->toBeTrue()
            ->and(DowngradeNotice::query()->whereNotNull('executed_action_id')->count())->toBe(10);

        // Nothing changed in Zoom.
        expect(ZoomMember::query()->where('zoom_user_id', 'u33_never2')->firstOrFail()->type)->toBe(2);
    });
});

test('with dry run off, due notices downgrade for real and respect the safety cap', function () {
    Notification::fake();
    enableAutomation($this->organization, ['dry_run' => false]);
    runAutomation($this->organization);

    // Make the cap tiny: 10% of 20 licensed → max(10, 2) = 10, so lower licensed_total drastically has no effect; instead add more due notices than 10.
    app(Tenancy::class)->runAs($this->organization, function () {
        Scan::query()->latest('id')->firstOrFail()->forceFill(['totals' => array_merge(Scan::query()->latest('id')->firstOrFail()->totals, ['licensed_total' => 20])])->save();
    });

    $result = runAutomation($this->organization, CarbonImmutable::now()->addDays(8));

    expect($result['executed'])->toBe(10)->and($result['deferred'])->toBe(1);

    app(Tenancy::class)->runAs($this->organization, function () {
        // The two fixture Zoom Rooms are idle and eligible until Zoom refuses them (error 200), so they show up as failed.
        $rule = LicenseAction::query()->where('source', 'rule')->get();
        expect($rule)->toHaveCount(10)
            ->and($rule->where('status', 'done')->count() + $rule->where('status', 'failed')->count())->toBe(10)
            ->and($rule->where('status', 'failed')->count())->toBeLessThanOrEqual(2)
            ->and(ZoomMember::query()->where('zoom_user_id', 'u33_never2')->firstOrFail()->type)->toBe(1)
            ->and(DowngradeNotice::query()->open()->count())->toBe(1);
    });

    // Next night picks up the deferred one.
    $result = runAutomation($this->organization, CarbonImmutable::now()->addDays(9));
    expect($result['executed'])->toBe(1);
});

test('keep my license works from the signed link without login, excludes the user for 90 days and tells admins', function () {
    Notification::fake();
    enableAutomation($this->organization);
    runAutomation($this->organization);

    $notice = app(Tenancy::class)->runAs($this->organization, fn () => DowngradeNotice::query()->open()->with('member')->firstOrFail());
    $url = URL::temporarySignedRoute('keep-license', now()->addDays(9), ['notice' => $notice->id]);

    $this->get($url)->assertOk()->assertSee('Keep my license')->assertSee($notice->member->email);

    $storeUrl = str_replace('/keep/'.$notice->id.'?', '/keep/'.$notice->id.'?', $url);
    $this->post($storeUrl)->assertRedirect();

    $notice->refresh();
    $member = $notice->member->fresh();
    expect($notice->kept_at)->not->toBeNull()
        ->and($notice->isOpen())->toBeFalse()
        ->and($member->excluded_until?->toDateString())->toBe(now()->addDays(90)->toDateString())
        ->and($member->eligible_for_downgrade)->toBeFalse()
        ->and($member->bucket)->toBe(Bucket::Protected);

    Notification::assertSentTo($this->user, LicenseKept::class);

    $this->get($url)->assertOk()->assertSee('Your Licensed account stays');

    // Tampered or expired links are rejected.
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    $this->get(URL::temporarySignedRoute('keep-license', now()->subMinute(), ['notice' => $notice->id]))->assertForbidden();

    // The next scan keeps the exclusion (excluded_until survives upserts).
    ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
    $after = $member->fresh();
    expect($after->bucket)->toBe(Bucket::Protected)->and($after->protected_reasons)->toContain('kept by the user until '.now()->addDays(90)->toDateString());
});

test('scheduled scans trigger automation, manual scans do not', function () {
    Queue::fake();
    (new ScanOrganization($this->organization->id, ScanOrganization::start($this->organization, Scan::TRIGGER_SCHEDULED)->id))->handle(app(Tenancy::class), app(ScanRunner::class));
    Queue::assertPushed(RunAutomation::class, 1);

    (new ScanOrganization($this->organization->id, ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL)->id))->handle(app(Tenancy::class), app(ScanRunner::class));
    Queue::assertPushed(RunAutomation::class, 1);
});

test('the weekly digest summarises the week and is sent on Monday 08:00 local time once', function () {
    Notification::fake();
    enableAutomation($this->organization, ['dry_run' => false]);
    $this->organization->update(['renewal_date' => now()->addDays(30)]);
    runAutomation($this->organization);
    runAutomation($this->organization, CarbonImmutable::now()->addDays(8));

    $data = app(Tenancy::class)->runAs($this->organization, fn () => DigestBuilder::build($this->organization));
    expect($data['downgraded'] + $data['skipped'])->toBe(10)->and($data['downgraded'])->toBeGreaterThanOrEqual(8)->and($data['pending'])->toBe(1)->and($data['renewal'])->toContain('reduce to');

    $admin = User::factory()->create();
    $viewer = User::factory()->create();
    $this->organization->addMember($admin, Role::Admin);
    $this->organization->addMember($viewer, Role::Viewer);

    $this->travelTo(CarbonImmutable::parse('2026-09-28 08:20', 'America/Chicago')); // a Monday
    $this->artisan('seattrim:send-weekly-digests')->assertSuccessful();
    Notification::assertSentTo([$this->user, $admin], WeeklyDigest::class);
    Notification::assertNotSentTo($viewer, WeeklyDigest::class);

    $this->artisan('seattrim:send-weekly-digests')->assertSuccessful();
    Notification::assertSentToTimes($this->user, WeeklyDigest::class, 1);

    $this->travelTo(CarbonImmutable::parse('2026-09-29 08:20', 'America/Chicago')); // Tuesday
    $this->artisan('seattrim:send-weekly-digests')->assertSuccessful();
    Notification::assertSentToTimes($this->user, WeeklyDigest::class, 1);

    $mail = (new WeeklyDigest($this->organization, $data))->toMail($this->user);
    expect($mail->subject)->toContain('Weekly Zoom license digest')
        ->and(implode("\n", array_map(fn ($l) => (string) $l, $mail->introLines)))->toContain('**Downgraded:** '.$data['downgraded'])->toContain('Idle licensed');
});

test('the automation page saves settings for admins and is read-only for viewers', function () {
    actingAsMemberOf($this->organization, $this->user)->test('pages::automation')
        ->set('enabled', true)
        ->set('dry_run', false)
        ->set('warning_days', 10)
        ->set('buckets', ['idle_licensed'])
        ->set('reply_to', 'helpdesk@example.edu')
        ->call('save')
        ->assertHasNoErrors();

    $settings = AutomationSettings::for($this->organization->fresh());
    expect($settings->enabled)->toBeTrue()->and($settings->dryRun)->toBeFalse()->and($settings->warningDays)->toBe(10)->and($settings->buckets)->toBe(['idle_licensed'])->and($settings->replyTo)->toBe('helpdesk@example.edu');

    actingAsMemberOf($this->organization, $this->user)->test('pages::automation')->set('warning_days', 0)->call('save')->assertHasErrors(['warning_days']);

    $viewer = User::factory()->create();
    $this->organization->addMember($viewer, Role::Viewer);
    actingAsMemberOf($this->organization, $viewer)->test('pages::automation')->set('enabled', false)->call('save')->assertForbidden();
    $this->actingAs($viewer)->get(route('automation'))->assertOk()->assertDontSee('data-test="save-automation"', false);
});

test('exclusions can be added and removed and are scoped to the organization', function () {
    actingAsMemberOf($this->organization, $this->user)->test('pages::exclusions')
        ->set('type', 'domain')->set('value', '@Board.Example.edu')->set('reason', 'board')
        ->call('add')->assertHasNoErrors();
    actingAsMemberOf($this->organization, $this->user)->test('pages::exclusions')
        ->set('type', 'email')->set('value', 'not-an-email')
        ->call('add')->assertHasErrors(['value']);
    actingAsMemberOf($this->organization, $this->user)->test('pages::exclusions')
        ->set('type', 'domain')->set('value', 'board.example.edu')
        ->call('add')->assertHasErrors(['value']);

    $exclusion = app(Tenancy::class)->runAs($this->organization, fn () => Exclusion::query()->firstOrFail());
    expect($exclusion->value)->toBe('board.example.edu')->and($exclusion->created_by)->toBe($this->user->id);

    $stranger = User::factory()->create();
    $strangerOrg = Organization::factory()->withMember($stranger)->create();
    $stranger->switchToOrganization($strangerOrg);
    $this->actingAs($stranger)->get(route('exclusions'))->assertOk()->assertDontSee('board.example.edu');
    actingAsMemberOf($strangerOrg, $stranger)->test('pages::exclusions')->call('remove', $exclusion->id)->assertNotFound();

    actingAsMemberOf($this->organization, $this->user)->test('pages::exclusions')->call('remove', $exclusion->id);
    expect(Exclusion::query()->allOrganizations()->count())->toBe(0);
});
