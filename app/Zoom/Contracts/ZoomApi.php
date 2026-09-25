<?php

namespace App\Zoom\Contracts;

use App\Models\ZoomConnection;
use App\Zoom\Data\ApiResult;
use App\Zoom\Data\HostReportRow;
use App\Zoom\Data\PagedList;
use App\Zoom\Data\PlanUsage;
use App\Zoom\Data\TokenSet;
use App\Zoom\Data\UpcomingMeeting;
use App\Zoom\Data\UserFeatures;
use App\Zoom\Data\UserSummary;
use App\Zoom\Data\ZoomUser;
use Carbon\CarbonInterface;

/**
 * The only doorway to Zoom. Implemented by ZoomClient (HTTP) and FakeZoomClient
 * (fixtures). Every method maps to exactly one endpoint listed in
 * docs/zoom-api-notes.md; nothing else in the app may call api.zoom.us.
 */
interface ZoomApi
{
    public function exchangeCode(string $code, string $redirectUri): TokenSet;

    public function refreshToken(string $refreshToken): TokenSet;

    public function revokeToken(string $accessToken): void;

    /** GET /users/me — identifies the installer and the account id. */
    public function me(ZoomConnection $connection): ZoomUser;

    /** GET /users/{userId} */
    public function getUser(ZoomConnection $connection, string $userId): ZoomUser;

    /**
     * GET /users?status=… followed to exhaustion.
     *
     * @return PagedList<ZoomUser>
     */
    public function listUsers(ZoomConnection $connection, string $status): PagedList;

    /** GET /users/summary */
    public function userSummary(ZoomConnection $connection): UserSummary;

    /** GET /users/{userId}/settings → feature */
    public function userFeatures(ZoomConnection $connection, string $userId): UserFeatures;

    /** PATCH /users/{userId} {"type": 1|2} */
    public function updateUserType(ZoomConnection $connection, string $userId, int $type): ApiResult;

    /**
     * GET /report/users?type=active|inactive&from&to, one ≤31-day window, followed to exhaustion.
     *
     * @return PagedList<HostReportRow>
     */
    public function hostReport(ZoomConnection $connection, string $type, CarbonInterface $from, CarbonInterface $to): PagedList;

    /** GET /accounts/me/plans/usage. Returns PlanUsage::unavailable() instead of throwing on 400/403. */
    public function planUsage(ZoomConnection $connection): PlanUsage;

    /**
     * GET /users/{userId}/meetings?type=upcoming, first page only (we only need "any?").
     *
     * @return array<int, UpcomingMeeting>
     */
    public function upcomingMeetings(ZoomConnection $connection, string $userId): array;
}
