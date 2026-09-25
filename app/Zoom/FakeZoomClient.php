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
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Fixture-driven ZoomApi. Powers the test suite and demo mode (ZOOM_DRIVER=fake).
 *
 * State that a real Zoom account would keep (user types after a downgrade) is
 * held in "overrides", persisted in the cache per Zoom account so demo mode
 * survives across requests. Tests get a fresh array cache each run.
 */
class FakeZoomClient implements ZoomApi
{
    public const DEMO_ACCOUNT_ID = 'demo-account';

    /** @var array<int, array{method: string, args: array<int|string, mixed>}> */
    public array $calls = [];

    /** @var array<string, ZoomApiException> method => exception thrown on next call */
    private array $failNext = [];

    private bool $planUsageUnavailable = false;

    private bool $partialUserList = false;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $usersOverride = null;

    public function __construct(private readonly FixtureSet $fixtures) {}

    // ---------------------------------------------------------- test controls

    public function failNext(string $method, ZoomApiException $exception): self
    {
        $this->failNext[$method] = $exception;

        return $this;
    }

    public function withPlanUsageUnavailable(bool $value = true): self
    {
        $this->planUsageUnavailable = $value;

        return $this;
    }

    /** Make the users list return one fewer row than total_records claims. */
    public function withPartialUserList(bool $value = true): self
    {
        $this->partialUserList = $value;

        return $this;
    }

    /**
     * Replace the fixture users entirely (raw Zoom-shaped arrays).
     *
     * @param  array<int, array<string, mixed>>  $users
     */
    public function withUsers(array $users): self
    {
        $this->usersOverride = array_values($users);

        return $this;
    }

    /**
     * Merge attributes into one fixture user (matched by id or email).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function patchUser(string $idOrEmail, array $attributes): self
    {
        $users = $this->usersOverride ?? $this->fixtures->loadList('users');
        foreach ($users as $i => $user) {
            if (($user['id'] ?? null) === $idOrEmail || ($user['email'] ?? null) === $idOrEmail) {
                $users[$i] = array_merge($user, $attributes);
            }
        }
        $this->usersOverride = array_values($users);

        return $this;
    }

    /** @return array<int, array{method: string, args: array<int|string, mixed>}> */
    public function callsTo(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c['method'] === $method));
    }

    public function resetOverrides(ZoomConnection $connection): void
    {
        Cache::forget($this->overridesKey($connection));
    }

    // ------------------------------------------------------------------ OAuth

    public function exchangeCode(string $code, string $redirectUri): TokenSet
    {
        $this->record(__FUNCTION__, func_get_args());

        return new TokenSet('fake-access-'.Str::random(8), 'fake-refresh-'.Str::random(8), 3600, implode(' ', (array) config('zoom.scopes')));
    }

    public function refreshToken(string $refreshToken): TokenSet
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->maybeFail(__FUNCTION__);

        return new TokenSet('fake-access-'.Str::random(8), 'fake-refresh-'.Str::random(8), 3600, implode(' ', (array) config('zoom.scopes')));
    }

    public function revokeToken(string $accessToken): void
    {
        $this->record(__FUNCTION__, ['[redacted]']);
        $this->maybeFail(__FUNCTION__);
    }

    // ------------------------------------------------------------------ Users

    public function me(ZoomConnection $connection): ZoomUser
    {
        $this->record(__FUNCTION__, []);

        $accountId = in_array($connection->zoom_account_id, ['', 'pending'], true)
            ? 'demo-'.$connection->organization_id
            : $connection->zoom_account_id;

        foreach ($this->users($connection) as $user) {
            if (($user['role_id'] ?? null) === '0') {
                return ZoomUser::fromArray($user + ['account_id' => $accountId]);
            }
        }

        throw new ZoomApiException('No owner in fixtures.', 404, 1001);
    }

    public function getUser(ZoomConnection $connection, string $userId): ZoomUser
    {
        $this->record(__FUNCTION__, [$userId]);
        $this->maybeFail(__FUNCTION__);

        foreach ($this->users($connection) as $user) {
            if (($user['id'] ?? null) === $userId || ($user['email'] ?? null) === $userId) {
                return ZoomUser::fromArray($user);
            }
        }

        throw new ZoomApiException("User does not exist: {$userId}", 404, 1001, 'fake-'.Str::random(6), "/users/{$userId}");
    }

    public function listUsers(ZoomConnection $connection, string $status): PagedList
    {
        $this->record(__FUNCTION__, [$status]);
        $this->maybeFail(__FUNCTION__);

        $rows = array_values(array_filter($this->users($connection), fn ($u) => ($u['status'] ?? 'active') === $status));
        $total = count($rows);

        if ($this->partialUserList && $status === ZoomUser::STATUS_ACTIVE && $total > 0) {
            array_pop($rows);
        }

        $items = array_map(function (array $row) use ($status) {
            // List rows never include single-user-only fields and pending users have no id.
            unset($row['zoom_one_type'], $row['role_name'], $row['account_id']);
            if ($status === ZoomUser::STATUS_PENDING) {
                unset($row['id']);
            }

            return ZoomUser::fromArray($row, $status);
        }, $rows);

        $pageSize = (int) config('zoom.page_size.users', 300);

        return new PagedList($items, max(1, (int) ceil($total / $pageSize)), $total, 'fake-'.Str::random(6));
    }

    public function userSummary(ZoomConnection $connection): UserSummary
    {
        $this->record(__FUNCTION__, []);

        $licensed = $basic = $pending = $rooms = $joinOnly = 0;
        $roomIds = $this->fixtures->load('rooms');

        foreach ($this->users($connection) as $u) {
            if (($u['status'] ?? 'active') === ZoomUser::STATUS_PENDING) {
                $pending++;

                continue;
            }
            if (in_array($u['id'] ?? '', $roomIds, true)) {
                $rooms++;

                continue;
            }
            match ((int) ($u['type'] ?? 1)) {
                ZoomUser::TYPE_LICENSED => $licensed++,
                ZoomUser::TYPE_UNASSIGNED => $joinOnly++,
                default => $basic++,
            };
        }

        return UserSummary::fromArray([
            'licensed_users_count' => $licensed,
            'basic_users_count' => $basic,
            'pending_users_count' => $pending,
            'room_users_count' => $rooms,
            'join_only_users_count' => $joinOnly,
            'on_prem_users_count' => 0,
            'total_users_count' => $licensed + $basic + $rooms + $joinOnly,
        ]);
    }

    public function userFeatures(ZoomConnection $connection, string $userId): UserFeatures
    {
        $this->record(__FUNCTION__, [$userId]);
        $this->maybeFail(__FUNCTION__);

        $features = $this->fixtures->load('features');

        return UserFeatures::fromArray((array) ($features[$userId] ?? ['zoom_phone' => false, 'webinar' => false, 'large_meeting' => false, 'meeting_capacity' => 100]));
    }

    public function updateUserType(ZoomConnection $connection, string $userId, int $type): ApiResult
    {
        $this->record(__FUNCTION__, [$userId, $type]);
        $this->maybeFail(__FUNCTION__);

        if (in_array($userId, $this->fixtures->load('rooms'), true) && $type === ZoomUser::TYPE_BASIC) {
            throw new ZoomApiException("A Zoom Room user cannot be changed to a free user type: {$userId}", 400, 200, 'fake-'.Str::random(6), "/users/{$userId}");
        }

        // Zoom accepts either the user id or the email address as {userId}.
        $resolvedId = null;
        foreach ($this->users($connection) as $user) {
            if (($user['id'] ?? null) === $userId || ($user['email'] ?? null) === $userId) {
                $resolvedId = (string) $user['id'];
            }
        }
        if ($resolvedId === null) {
            throw new ZoomApiException("User does not exist: {$userId}", 404, 1001, 'fake-'.Str::random(6), "/users/{$userId}");
        }

        $overrides = $this->overrides($connection);
        $overrides[$resolvedId]['type'] = $type;
        Cache::forever($this->overridesKey($connection), $overrides);

        return new ApiResult('fake-'.Str::random(6));
    }

    // ---------------------------------------------------------------- Reports

    public function hostReport(ZoomConnection $connection, string $type, CarbonInterface $from, CarbonInterface $to): PagedList
    {
        $this->record(__FUNCTION__, [$type, $from->toDateString(), $to->toDateString()]);
        $this->maybeFail(__FUNCTION__);

        if ($to->diffInDays($from, true) > 31) {
            throw new ZoomApiException('The date range defined by the from and to parameters should only be one month.', 400, 300, null, '/report/users');
        }

        $hosting = $this->fixtures->load('hosting');
        $rows = [];

        foreach ($this->users($connection) as $user) {
            if (($user['status'] ?? 'active') === ZoomUser::STATUS_PENDING || empty($user['id'])) {
                continue;
            }

            $meetings = 0;
            foreach ((array) ($hosting[$user['id']] ?? []) as $date) {
                $day = CarbonImmutable::parse((string) $date);
                if ($day->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                    $meetings++;
                }
            }

            $include = $type === 'active' ? $meetings > 0 : $meetings === 0;
            if (! $include) {
                continue;
            }

            $rows[] = HostReportRow::fromArray([
                'id' => $user['id'],
                'email' => $user['email'],
                'user_name' => $user['display_name'] ?? trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')),
                'dept' => $user['dept'] ?? null,
                'type' => $user['type'] ?? 1,
                'meetings' => $meetings,
                'meeting_minutes' => $meetings * 37,
                'participants' => $meetings * 4,
            ]);
        }

        $pageSize = (int) config('zoom.page_size.report_users', 300);

        return new PagedList($rows, max(1, (int) ceil(count($rows) / $pageSize)), count($rows), 'fake-'.Str::random(6));
    }

    public function planUsage(ZoomConnection $connection): PlanUsage
    {
        $this->record(__FUNCTION__, []);

        if ($this->planUsageUnavailable) {
            return PlanUsage::unavailable('Zoom error 200: Only available for paid account.');
        }

        return PlanUsage::fromArray($this->fixtures->loadAssoc('plan_usage'));
    }

    public function upcomingMeetings(ZoomConnection $connection, string $userId): array
    {
        $this->record(__FUNCTION__, [$userId]);
        $this->maybeFail(__FUNCTION__);

        $all = $this->fixtures->load('upcoming_meetings');

        return array_map(fn (array $m) => UpcomingMeeting::fromArray($m), array_values((array) ($all[$userId] ?? [])));
    }

    // --------------------------------------------------------------- internals

    /**
     * Fixture users with per-account overrides (type changes) applied.
     *
     * @return array<int, array<string, mixed>>
     */
    private function users(ZoomConnection $connection): array
    {
        $users = $this->usersOverride ?? $this->fixtures->loadList('users');
        $overrides = $this->overrides($connection);

        foreach ($users as $i => $user) {
            if (isset($user['id'], $overrides[$user['id']])) {
                $users[$i] = array_merge($user, $overrides[$user['id']]);
            }
        }

        return $users;
    }

    /** @return array<string, array<string, mixed>> */
    private function overrides(ZoomConnection $connection): array
    {
        return (array) Cache::get($this->overridesKey($connection), []);
    }

    private function overridesKey(ZoomConnection $connection): string
    {
        return 'zoom-fake:overrides:'.($connection->zoom_account_id ?: self::DEMO_ACCOUNT_ID);
    }

    /** @param array<int|string, mixed> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];
    }

    private function maybeFail(string $method): void
    {
        if (isset($this->failNext[$method])) {
            $e = $this->failNext[$method];
            unset($this->failNext[$method]);

            throw $e;
        }
    }
}
