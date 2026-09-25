<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\TokenManager;
use App\Zoom\ZoomClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config(['zoom.driver' => 'http', 'zoom.client_id' => 'cid', 'zoom.client_secret' => 'secret']);
    Sleep::fake();
    $organization = Organization::factory()->withMember(User::factory()->create())->create();
    $this->connection = app(Tenancy::class)->runAs($organization, fn () => ZoomConnection::factory()->create(['access_token' => 'tok', 'expires_at' => now()->addHour()]));
    $this->client = new ZoomClient(app(TokenManager::class));
});

test('listUsers follows next_page_token to exhaustion and keeps other params identical', function () {
    Http::fake([
        'api.zoom.us/v2/users*' => Http::sequence()
            ->push(['users' => [['id' => 'a', 'email' => 'a@x', 'type' => 2]], 'next_page_token' => 'p2', 'total_records' => 3, 'page_size' => 1], 200, ['x-zm-trackingid' => 'trk-1'])
            ->push(['users' => [['id' => 'b', 'email' => 'b@x', 'type' => 1]], 'next_page_token' => 'p3', 'total_records' => 3])
            ->push(['users' => [['id' => 'c', 'email' => 'c@x', 'type' => 2]], 'next_page_token' => '', 'total_records' => 3]),
    ]);

    $list = $this->client->listUsers($this->connection, 'inactive');

    expect($list->count())->toBe(3)
        ->and($list->pages)->toBe(3)
        ->and($list->totalRecords)->toBe(3)
        ->and($list->isPartial())->toBeFalse()
        ->and($list->trackingId)->toBe('trk-1')
        ->and($list->items[0]->status)->toBe('inactive');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => $r['status'] === 'inactive' && $r['page_size'] === 300 && ! isset($r['next_page_token']));
    Http::assertSent(fn (Request $r) => $r['status'] === 'inactive' && $r['page_size'] === 300 && ($r->data()['next_page_token'] ?? null) === 'p2');
    Http::assertSent(fn (Request $r) => ($r->data()['next_page_token'] ?? null) === 'p3' && $r->hasHeader('Authorization', 'Bearer tok'));
});

test('a partial list is detectable when total_records disagrees with the rows received', function () {
    Http::fake(['api.zoom.us/v2/users*' => Http::response(['users' => [['id' => 'a', 'email' => 'a@x', 'type' => 2]], 'next_page_token' => '', 'total_records' => 5])]);

    expect($this->client->listUsers($this->connection, 'active')->isPartial())->toBeTrue();
});

test('429 honours Retry-After and then succeeds', function () {
    Http::fake([
        'api.zoom.us/v2/users/summary' => Http::sequence()
            ->push(['code' => 429, 'message' => 'You have reached the maximum per-second rate limit for this API. Try again later.'], 429, ['Retry-After' => '3'])
            ->push(['licensed_users_count' => 7, 'basic_users_count' => 2]),
    ]);

    $summary = $this->client->userSummary($this->connection);

    expect($summary->licensed)->toBe(7);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 3.0, times: 1);
    Http::assertSentCount(2);
});

test('an expired token answer triggers one forced refresh and a retry', function () {
    $this->connection->forceFill(['refresh_token' => 'r1'])->save();

    Http::fake([
        'zoom.us/oauth/token' => Http::response(['access_token' => 'new-tok', 'refresh_token' => 'r2', 'expires_in' => 3600, 'scope' => 'user:read:user:admin']),
        'api.zoom.us/v2/users/me' => Http::sequence()
            ->push(['code' => 124, 'message' => 'Invalid access token.'], 401)
            ->push(['id' => 'me', 'email' => 'me@x', 'type' => 2, 'account_id' => 'acct']),
    ]);

    $me = $this->client->me($this->connection);

    expect($me->id)->toBe('me')
        ->and($this->connection->fresh()->access_token)->toBe('new-tok')
        ->and($this->connection->fresh()->refresh_token)->toBe('r2');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth/token') && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'r1' && $r->hasHeader('Authorization', 'Basic '.base64_encode('cid:secret')));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/users/me') && $r->hasHeader('Authorization', 'Bearer new-tok'));
});

test('zoom errors become ZoomApiException with code, status and tracking id', function () {
    Http::fake(['api.zoom.us/v2/users/u1' => Http::response(['code' => 1001, 'message' => 'User does not exist: u1'], 404, ['x-zm-trackingid' => 'trk-404'])]);

    try {
        $this->client->getUser($this->connection, 'u1');
        $this->fail('expected exception');
    } catch (ZoomApiException $e) {
        expect($e->zoomCode)->toBe(1001)
            ->and($e->httpStatus)->toBe(404)
            ->and($e->trackingId)->toBe('trk-404')
            ->and($e->isNotFound())->toBeTrue()
            ->and($e->summary())->toBe('Zoom error 1001: User does not exist: u1');
    }
});

test('updateUserType patches only the type and returns the tracking id', function () {
    Http::fake(['api.zoom.us/v2/users/u1' => Http::response('', 204, ['x-zm-trackingid' => 'trk-patch'])]);

    $result = $this->client->updateUserType($this->connection, 'u1', 1);

    expect($result->trackingId)->toBe('trk-patch')->and($result->httpStatus)->toBe(204);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->data() === ['type' => 1]);
});

test('plan usage 400 degrades to unavailable instead of throwing', function () {
    Http::fake(['api.zoom.us/v2/accounts/me/plans/usage' => Http::response(['code' => 200, 'message' => 'Only available for paid account.'], 400)]);

    $usage = $this->client->planUsage($this->connection);

    expect($usage->available)->toBeFalse()->and($usage->unassigned())->toBeNull();
});

test('host report sends the documented query params', function () {
    Http::fake(['api.zoom.us/v2/report/users*' => Http::response(['users' => [['id' => 'a', 'email' => 'a@x', 'meetings' => 4]], 'next_page_token' => '', 'total_records' => 1])]);

    $report = $this->client->hostReport($this->connection, 'active', now()->subDays(30), now());

    expect($report->items[0]->meetings)->toBe(4);
    Http::assertSent(fn (Request $r) => $r['type'] === 'active' && $r['from'] === now()->subDays(30)->toDateString() && $r['to'] === now()->toDateString() && $r['page_size'] === 300);
});

test('5xx is retried with backoff and eventually thrown', function () {
    Http::fake(['api.zoom.us/v2/users/summary' => Http::response(['message' => 'boom'], 503)]);

    expect(fn () => $this->client->userSummary($this->connection))->toThrow(ZoomApiException::class);
    Http::assertSentCount(5);
    Sleep::assertSleptTimes(4);
});
