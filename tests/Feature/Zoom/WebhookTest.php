<?php

use App\Actions\Zoom\DisconnectZoom;
use App\Actions\Zoom\PurgeZoomData;
use App\Jobs\HandleZoomWebhook;
use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Notifications\ZoomDisconnected;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['zoom.webhook_secret_token' => 'shh']);
});

function signedWebhook(array $body, ?string $secret = 'shh', ?int $timestamp = null): array
{
    $timestamp ??= time();
    $raw = json_encode($body, JSON_UNESCAPED_SLASHES);

    return [
        'raw' => $raw,
        'headers' => [
            'x-zm-request-timestamp' => (string) $timestamp,
            'x-zm-signature' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$raw}", $secret ?? ''),
            'Content-Type' => 'application/json',
        ],
    ];
}

test('the CRC challenge is answered with the hashed plainToken', function () {
    $req = signedWebhook(['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'qgg8vlvZRS6UYooatFL8Aw'], 'event_ts' => 1]);

    $this->call('POST', '/zoom/webhook', [], [], [], $this->transformHeadersToServerVars($req['headers']), $req['raw'])
        ->assertOk()
        ->assertExactJson([
            'plainToken' => 'qgg8vlvZRS6UYooatFL8Aw',
            'encryptedToken' => hash_hmac('sha256', 'qgg8vlvZRS6UYooatFL8Aw', 'shh'),
        ]);
});

test('a bad signature is rejected with 401 and nothing is queued', function () {
    Queue::fake();
    $req = signedWebhook(['event' => 'app_deauthorized', 'payload' => ['account_id' => 'a'], 'event_ts' => 1], secret: 'wrong');

    $this->call('POST', '/zoom/webhook', [], [], [], $this->transformHeadersToServerVars($req['headers']), $req['raw'])->assertStatus(401);
    Queue::assertNothingPushed();
});

test('a stale timestamp is rejected', function () {
    $req = signedWebhook(['event' => 'endpoint.url_validation', 'payload' => ['plainToken' => 'x'], 'event_ts' => 1], timestamp: time() - 3600);

    $this->call('POST', '/zoom/webhook', [], [], [], $this->transformHeadersToServerVars($req['headers']), $req['raw'])->assertStatus(401);
});

test('a verified event is queued and answered with 204 without csrf', function () {
    Queue::fake();
    $req = signedWebhook(['event' => 'user.deactivated', 'payload' => ['account_id' => 'acct', 'object' => ['id' => 'u1']], 'event_ts' => 1]);

    $this->call('POST', '/zoom/webhook', [], [], [], $this->transformHeadersToServerVars($req['headers']), $req['raw'])->assertNoContent();
    Queue::assertPushed(HandleZoomWebhook::class, fn ($job) => $job->event === 'user.deactivated' && $job->payload['object']['id'] === 'u1');
});

test('app_deauthorized revokes, purges all Zoom data for the account and emails the owners', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->create();
    $other = Organization::factory()->withMember(User::factory()->create())->create();
    app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create(['zoom_account_id' => 'acct-gone']));
    app(Tenancy::class)->runAs($other, fn () => ZoomConnection::factory()->create(['zoom_account_id' => 'acct-stays']));

    (new HandleZoomWebhook('app_deauthorized', ['account_id' => 'acct-gone', 'user_id' => 'u', 'client_id' => 'c', 'deauthorization_time' => now()->toIso8601String(), 'signature' => 's'], time()))
        ->handle(app(DisconnectZoom::class));

    expect(ZoomConnection::query()->allOrganizations()->where('zoom_account_id', 'acct-gone')->exists())->toBeFalse()
        ->and(ZoomConnection::query()->allOrganizations()->where('zoom_account_id', 'acct-stays')->exists())->toBeTrue();

    $organization->refresh();
    expect($organization->setting('zoom.disconnect_reason'))->toBe('deauthorized')
        ->and($organization->setting('zoom.disconnected_at'))->not->toBeNull()
        ->and($organization->setting('zoom.purged_at'))->not->toBeNull();

    Notification::assertSentTo($owner, ZoomDisconnected::class);

    // Redelivery is harmless.
    (new HandleZoomWebhook('app_deauthorized', ['account_id' => 'acct-gone'], time()))->handle(app(DisconnectZoom::class));
});

test('purge only touches the given organization', function () {
    $a = Organization::factory()->withMember(User::factory()->create())->create();
    $b = Organization::factory()->withMember(User::factory()->create())->create();
    app(Tenancy::class)->runAs($a, fn () => ZoomConnection::factory()->create());
    app(Tenancy::class)->runAs($b, fn () => ZoomConnection::factory()->create());

    $deleted = app(PurgeZoomData::class)->handle($a);

    expect($deleted['zoom_connections'])->toBe(1)
        ->and(ZoomConnection::query()->allOrganizations()->count())->toBe(1)
        ->and(ZoomConnection::query()->allOrganizations()->first()?->organization_id)->toBe($b->id);
});
