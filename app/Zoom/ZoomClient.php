<?php

namespace App\Zoom;

use App\Models\ZoomConnection;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\ApiResult;
use App\Zoom\Data\HostReportRow;
use App\Zoom\Data\PagedList;
use App\Zoom\Data\PlanUsage;
use App\Zoom\Data\TokenSet;
use App\Zoom\Data\UpcomingMeeting;
use App\Zoom\Data\UserFeatures;
use App\Zoom\Data\UserSummary;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ZoomApiException;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The HTTP implementation of ZoomApi. Endpoint paths, params and field names
 * are the ones recorded in docs/zoom-api-notes.md; do not add calls that are
 * not listed there.
 */
class ZoomClient implements ZoomApi
{
    private const MAX_ATTEMPTS = 5;

    private const MAX_BACKOFF_SECONDS = 60;

    public function __construct(private readonly TokenManager $tokens) {}

    // ---------------------------------------------------------------- OAuth

    public function exchangeCode(string $code, string $redirectUri): TokenSet
    {
        return $this->oauth(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]);
    }

    public function refreshToken(string $refreshToken): TokenSet
    {
        return $this->oauth(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    public function revokeToken(string $accessToken): void
    {
        $response = $this->oauthRequest()->asForm()->post($this->oauthUrl('revoke'), ['token' => $accessToken]);

        if (! $response->successful()) {
            throw $this->exceptionFor($response, 'oauth/revoke');
        }
    }

    /** @param array<string, string> $params */
    private function oauth(array $params): TokenSet
    {
        $response = $this->oauthRequest()->asForm()->post($this->oauthUrl('token'), $params);

        if (! $response->successful()) {
            throw $this->exceptionFor($response, 'oauth/token');
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (empty($body['access_token'])) {
            throw new ZoomApiException('Token response had no access_token.', $response->status(), null, $this->trackingId($response), 'oauth/token');
        }

        return TokenSet::fromArray($body);
    }

    private function oauthRequest(): PendingRequest
    {
        return Http::withBasicAuth((string) config('zoom.client_id'), (string) config('zoom.client_secret'))
            ->acceptJson()
            ->timeout(20);
    }

    private function oauthUrl(string $path): string
    {
        return rtrim((string) config('zoom.oauth_base_url'), '/').'/'.$path;
    }

    // ---------------------------------------------------------------- Users

    public function me(ZoomConnection $connection): ZoomUser
    {
        return ZoomUser::fromArray($this->get($connection, '/users/me')->json() ?? []);
    }

    public function getUser(ZoomConnection $connection, string $userId): ZoomUser
    {
        return ZoomUser::fromArray($this->get($connection, '/users/'.rawurlencode($userId))->json() ?? []);
    }

    public function listUsers(ZoomConnection $connection, string $status): PagedList
    {
        return $this->paginate(
            $connection,
            '/users',
            ['status' => $status, 'page_size' => (int) config('zoom.page_size.users', 300)],
            'users',
            fn (array $row) => ZoomUser::fromArray($row, $status),
        );
    }

    public function userSummary(ZoomConnection $connection): UserSummary
    {
        return UserSummary::fromArray($this->get($connection, '/users/summary')->json() ?? []);
    }

    public function userFeatures(ZoomConnection $connection, string $userId): UserFeatures
    {
        $body = $this->get($connection, '/users/'.rawurlencode($userId).'/settings')->json() ?? [];

        return UserFeatures::fromArray((array) ($body['feature'] ?? []));
    }

    public function updateUserType(ZoomConnection $connection, string $userId, int $type): ApiResult
    {
        $response = $this->request($connection, 'patch', '/users/'.rawurlencode($userId), json: ['type' => $type]);

        return new ApiResult($this->trackingId($response), $response->status());
    }

    // -------------------------------------------------------------- Reports

    public function hostReport(ZoomConnection $connection, string $type, CarbonInterface $from, CarbonInterface $to): PagedList
    {
        return $this->paginate(
            $connection,
            '/report/users',
            [
                'type' => $type,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'page_size' => (int) config('zoom.page_size.report_users', 300),
            ],
            'users',
            fn (array $row) => HostReportRow::fromArray($row),
        );
    }

    public function planUsage(ZoomConnection $connection): PlanUsage
    {
        try {
            return PlanUsage::fromArray($this->get($connection, '/accounts/me/plans/usage')->json() ?? []);
        } catch (ZoomApiException $e) {
            if (in_array($e->httpStatus, [400, 403, 404], true)) {
                Log::notice('zoom.plan_usage.unavailable', ['connection' => $connection->describe(), 'error' => $e->summary()]);

                return PlanUsage::unavailable($e->summary());
            }

            throw $e;
        }
    }

    public function upcomingMeetings(ZoomConnection $connection, string $userId): array
    {
        $body = $this->get($connection, '/users/'.rawurlencode($userId).'/meetings', [
            'type' => 'upcoming',
            'page_size' => (int) config('zoom.page_size.meetings', 300),
        ])->json() ?? [];

        return array_map(fn (array $m) => UpcomingMeeting::fromArray($m), array_values((array) ($body['meetings'] ?? [])));
    }

    // ------------------------------------------------------------- Plumbing

    /** @param array<string, mixed> $query */
    private function get(ZoomConnection $connection, string $path, array $query = []): Response
    {
        return $this->request($connection, 'get', $path, $query);
    }

    /**
     * Follow next_page_token to exhaustion. Keeps every other query parameter
     * identical between pages, as the pagination guide requires.
     *
     * @template T
     *
     * @param  array<string, mixed>  $query
     * @param  Closure(array<string, mixed>): T  $map
     * @return PagedList<T>
     */
    private function paginate(ZoomConnection $connection, string $path, array $query, string $itemsKey, Closure $map): PagedList
    {
        $items = [];
        $pages = 0;
        $totalRecords = null;
        $trackingId = null;
        $token = null;

        do {
            $response = $this->get($connection, $path, $token !== null ? $query + ['next_page_token' => $token] : $query);
            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];
            $pages++;
            $trackingId ??= $this->trackingId($response);
            $totalRecords ??= isset($body['total_records']) ? (int) $body['total_records'] : null;

            foreach ((array) ($body[$itemsKey] ?? []) as $row) {
                $items[] = $map((array) $row);
            }

            $token = isset($body['next_page_token']) && $body['next_page_token'] !== '' ? (string) $body['next_page_token'] : null;

            if ($pages > 200) {
                throw new ZoomApiException("Pagination of {$path} exceeded 200 pages; aborting to avoid a loop.", 0, null, $trackingId, $path);
            }
        } while ($token !== null);

        Log::debug('zoom.paginate', ['path' => $path, 'pages' => $pages, 'items' => count($items), 'total_records' => $totalRecords, 'connection' => $connection->describe()]);

        return new PagedList($items, $pages, $totalRecords, $trackingId);
    }

    /**
     * One API call with the retry policy from docs/zoom-api-notes.md §9:
     * 429 → honour Retry-After (else exponential), 5xx → exponential with jitter,
     * 401 / code 124 / 4700 → refresh once and retry.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    private function request(ZoomConnection $connection, string $method, string $path, array $query = [], ?array $json = null): Response
    {
        $url = rtrim((string) config('zoom.api_base_url'), '/').$path;
        $accessToken = $this->tokens->accessToken($connection);
        $refreshedOnce = false;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $pending = Http::withToken($accessToken)->acceptJson()->timeout(30);

            try {
                $response = match ($method) {
                    'get' => $pending->get($url, $query),
                    'patch' => $pending->withQueryParameters($query)->patch($url, $json ?? []),
                    default => throw new \InvalidArgumentException("Unsupported method {$method}"),
                };
            } catch (ConnectionException $e) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw new ZoomApiException('Could not reach Zoom: '.$e->getMessage(), 0, null, null, $path);
                }
                $this->backoff($attempt);

                continue;
            }

            if ($response->successful()) {
                return $response;
            }

            $code = (int) ($response->json('code') ?? 0);

            if ($response->status() === 429) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $this->exceptionFor($response, $path);
                }
                $retryAfter = (int) $response->header('Retry-After');
                Log::notice('zoom.rate_limited', ['path' => $path, 'attempt' => $attempt, 'retry_after' => $retryAfter, 'connection' => $connection->describe()]);
                $this->backoff($attempt, $retryAfter > 0 ? $retryAfter : null);

                continue;
            }

            if ($response->serverError()) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $this->exceptionFor($response, $path);
                }
                $this->backoff($attempt);

                continue;
            }

            if (! $refreshedOnce && ($response->status() === 401 || in_array($code, [124, 4700], true))) {
                $refreshedOnce = true;
                $accessToken = $this->tokens->refresh($connection, force: true);

                continue;
            }

            throw $this->exceptionFor($response, $path);
        }

        throw new ZoomApiException("Gave up calling {$path} after ".self::MAX_ATTEMPTS.' attempts.', 0, null, null, $path);
    }

    private function backoff(int $attempt, ?int $seconds = null): void
    {
        $seconds ??= (2 ** ($attempt - 1)) + random_int(0, 1000) / 1000;

        Sleep::for(min($seconds, self::MAX_BACKOFF_SECONDS))->seconds();
    }

    private function exceptionFor(Response $response, string $endpoint): ZoomApiException
    {
        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $message = (string) ($body['message'] ?? $body['reason'] ?? $body['error'] ?? $response->reason());

        return new ZoomApiException(
            $message !== '' ? $message : "HTTP {$response->status()}",
            $response->status(),
            isset($body['code']) ? (int) $body['code'] : null,
            $this->trackingId($response),
            $endpoint,
            $body,
        );
    }

    private function trackingId(Response $response): ?string
    {
        $id = $response->header('x-zm-trackingid');

        return $id !== '' ? $id : null;
    }
}
