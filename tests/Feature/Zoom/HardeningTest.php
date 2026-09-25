<?php

use App\Jobs\DowngradeMember;
use App\Jobs\HandleZoomWebhook;
use App\Jobs\ScanOrganization;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Tenancy\Tenancy;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->user)->create();
    $this->user->switchToOrganization($this->organization);
    onPaidPlan($this->organization);
});

test('a scan against a revoked connection fails cleanly and never leaves a running row', function () {
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->revoked()->create());

    $scan = ScanOrganization::start($this->organization);
    $scan = app(Tenancy::class)->runAs($this->organization, fn () => Scan::query()->findOrFail($scan->id));

    expect($scan->status)->toBe('failed')->and($scan->error)->toContain('not connected');
});

test('a scan whose token refresh is refused mid-run is marked failed with a reconnect hint', function () {
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    app(FakeZoomClient::class)->failNext('listUsers', new ConnectionRevokedException('refresh refused: invalid_grant'));

    $scan = ScanOrganization::start($this->organization);
    $scan = app(Tenancy::class)->runAs($this->organization, fn () => Scan::query()->findOrFail($scan->id));

    expect($scan->status)->toBe('failed')->and($scan->error)->toContain('revoked');
});

test('the scan job crash handler marks the scan failed instead of leaving it running', function () {
    $scan = app(Tenancy::class)->runAs($this->organization, fn () => Scan::query()->create(['trigger' => 'manual', 'status' => 'running', 'started_at' => now()]));

    (new ScanOrganization($this->organization->id, $scan->id))->failed(new RuntimeException('worker timed out'));

    expect($scan->fresh()->status)->toBe('failed')->and($scan->fresh()->error)->toContain('worker timed out');
});

test('the downgrade job crash handler never leaves a queued audit row', function () {
    $member = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::factory()->idle()->create());
    $action = app(Tenancy::class)->runAs($this->organization, fn () => LicenseAction::query()->create(['zoom_member_id' => $member->id, 'action' => 'downgrade', 'source' => 'manual', 'status' => 'queued']));

    (new DowngradeMember($this->organization->id, $action->id))->failed(new RuntimeException('db gone'));

    expect($action->fresh()->status)->toBe('failed')->and($action->fresh()->reason)->toContain('db gone');
});

test('scans and automation cannot overlap per organization and webhooks retry with backoff', function () {
    $middleware = (new ScanOrganization($this->organization->id, 1))->middleware();
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);

    $job = new HandleZoomWebhook('user.updated', [], 0);
    expect($job->tries)->toBe(3)->and($job->backoff)->toBe([30, 300, 1800]);
});

test('tokens never appear in logs when a connection is described', function () {
    $connection = app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create(['access_token' => 'super-secret-access', 'refresh_token' => 'super-secret-refresh']));

    expect($connection->describe())->not->toContain('super-secret')
        ->and(json_encode($connection))->not->toContain('super-secret')
        ->and($connection->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);

    Log::shouldReceive('info')->once()->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'super-secret'));
    Log::info('probe', ['connection' => $connection->describe()]);
});

test('rate limiting on the public demo route is enforced', function () {
    $route = app('router')->getRoutes()->getByName('demo');
    expect($route->gatherMiddleware())->toContain('throttle:10,1');
});

test('the runner tolerates a partial report failure and still finishes with warnings', function () {
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    $fake = app(FakeZoomClient::class);
    $fake->failNext('userFeatures', new ZoomApiException('You have reached the maximum per-second rate limit for this API. Try again later.', 429));

    $scan = ScanOrganization::start($this->organization);
    $scan = app(Tenancy::class)->runAs($this->organization, fn () => Scan::query()->findOrFail($scan->id));

    expect($scan->status)->toBe('done')
        ->and(collect($scan->warnings)->pluck('code'))->toContain('detail_failed');
});
