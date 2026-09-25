<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Notifications\ZoomConnectionRevoked;
use App\Tenancy\Tenancy;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use App\Zoom\TokenManager;
use Illuminate\Support\Facades\Notification;

function connectionFor(?Organization $organization = null, array $state = []): ZoomConnection
{
    $organization ??= Organization::factory()->withMember(User::factory()->create())->create();

    return app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create($state));
}

test('a fresh token is returned without refreshing', function () {
    $connection = connectionFor(state: ['access_token' => 'still-good', 'expires_at' => now()->addMinutes(30)]);

    expect(app(TokenManager::class)->accessToken($connection))->toBe('still-good')
        ->and(app(FakeZoomClient::class)->callsTo('refreshToken'))->toBe([]);
});

test('a token about to expire is refreshed and the rotated refresh token persisted', function () {
    $connection = connectionFor(state: ['access_token' => 'old', 'refresh_token' => 'old-refresh', 'expires_at' => now()->addMinutes(2)]);

    $token = app(TokenManager::class)->accessToken($connection);

    $connection->refresh();
    expect($token)->toStartWith('fake-access-')
        ->and($connection->access_token)->toBe($token)
        ->and($connection->refresh_token)->toStartWith('fake-refresh-')
        ->and($connection->refresh_token)->not->toBe('old-refresh')
        ->and($connection->expires_at?->isAfter(now()->addMinutes(50)))->toBeTrue()
        ->and($connection->last_refreshed_at)->not->toBeNull();

    // A second call within the window does not refresh again.
    app(TokenManager::class)->accessToken($connection);
    expect(app(FakeZoomClient::class)->callsTo('refreshToken'))->toHaveCount(1);
});

test('a 4xx from the token endpoint marks the connection revoked and emails the owners', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->create();
    $connection = connectionFor($organization, ['expires_at' => now()->subMinute()]);

    app(FakeZoomClient::class)->failNext('refreshToken', new ZoomApiException('invalid_grant', 400, null, null, 'oauth/token'));

    expect(fn () => app(TokenManager::class)->accessToken($connection))->toThrow(ConnectionRevokedException::class);

    $connection->refresh();
    expect($connection->status)->toBe(ZoomConnection::STATUS_REVOKED)
        ->and($connection->access_token)->toBeNull()
        ->and($connection->refresh_token)->toBeNull()
        ->and($connection->last_error)->toContain('invalid_grant');

    Notification::assertSentTo($owner, ZoomConnectionRevoked::class);
});

test('a 5xx from the token endpoint is not treated as revocation', function () {
    $connection = connectionFor(state: ['expires_at' => now()->subMinute()]);
    app(FakeZoomClient::class)->failNext('refreshToken', new ZoomApiException('upstream down', 503));

    expect(fn () => app(TokenManager::class)->accessToken($connection))->toThrow(ZoomApiException::class);
    expect($connection->fresh()->status)->toBe(ZoomConnection::STATUS_ACTIVE);
});

test('a revoked connection never hands out a token', function () {
    $connection = connectionFor(state: ['status' => ZoomConnection::STATUS_REVOKED, 'access_token' => null]);

    expect(fn () => app(TokenManager::class)->accessToken($connection))->toThrow(ConnectionRevokedException::class);
});
