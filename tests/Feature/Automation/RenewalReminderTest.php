<?php

use App\Enums\Role;
use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Notifications\RenewalReminder;
use App\Renewal\RenewalFigures;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->viewer = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->owner)->withMember($this->admin, Role::Admin)->withMember($this->viewer, Role::Viewer)->create(['timezone' => 'Europe/London', 'seat_price_cents' => 15000]);
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
});

test('renewal figures match the dashboard maths', function () {
    $this->organization->update(['renewal_date' => now()->addDays(45)]);
    $f = app(Tenancy::class)->runAs($this->organization, fn () => RenewalFigures::for($this->organization));

    expect($f['reclaimable'])->toBe(25)
        ->and($f['target'])->toBe($f['purchased'] - 25)
        ->and($f['target_money'])->toBe('$'.number_format(($f['purchased'] - 25) * 150))
        ->and($f['days_left'])->toBe(45);
});

test('reminders go to owners and admins at 60, 30 and 7 days, once each, at 09:00 local', function () {
    $this->organization->update(['renewal_date' => CarbonImmutable::parse('2026-12-01')]);

    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:30', 'Europe/London')); // 60 days before
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentTo([$this->owner, $this->admin], RenewalReminder::class, fn ($n) => $n->daysBefore === 60 && $n->figures['reclaimable'] === 25);
    Notification::assertNotSentTo($this->viewer, RenewalReminder::class);

    $this->travelTo(CarbonImmutable::parse('2026-10-03 09:30', 'Europe/London'));
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 1);

    $this->travelTo(CarbonImmutable::parse('2026-11-01 14:00', 'Europe/London')); // 30 days, wrong hour
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 1);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 09:10', 'Europe/London')); // missed the exact day: still fires the 30-day milestone
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 2);
    Notification::assertSentTo($this->owner, RenewalReminder::class, fn ($n) => $n->daysBefore === 28);

    $this->travelTo(CarbonImmutable::parse('2026-11-24 09:10', 'Europe/London')); // 7 days
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 3);

    $this->travelTo(CarbonImmutable::parse('2026-12-02 09:10', 'Europe/London')); // after renewal: nothing
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 3);

    // A new renewal date starts the cycle again.
    $this->organization->update(['renewal_date' => CarbonImmutable::parse('2027-12-01')]);
    $this->travelTo(CarbonImmutable::parse('2027-10-02 09:30', 'Europe/London'));
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->owner, RenewalReminder::class, 4);
});

test('no reminder without a renewal date, and the mail carries the numbers', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:30', 'Europe/London'));
    $this->artisan('seattrim:send-renewal-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->organization->update(['renewal_date' => CarbonImmutable::parse('2026-12-01')]);
    $figures = app(Tenancy::class)->runAs($this->organization, fn () => RenewalFigures::for($this->organization));
    $mail = (new RenewalReminder($this->organization, 60, $figures))->toMail($this->owner);

    expect($mail->subject)->toContain('renews in 60 days')
        ->and(implode(' ', array_map('strval', $mail->introLines)))->toContain('Reduce to **'.$figures['target'].'** seats')
        ->and($mail->actionUrl)->toBe('https://zoom.us/billing');
});
