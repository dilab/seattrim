<?php

namespace App\Zoom;

use App\Models\ZoomConnection;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\ApiResult;
use App\Zoom\Data\PagedList;
use App\Zoom\Data\PlanUsage;
use App\Zoom\Data\TokenSet;
use App\Zoom\Data\UserFeatures;
use App\Zoom\Data\UserSummary;
use App\Zoom\Data\ZoomUser;
use Carbon\CarbonInterface;

/**
 * Routes each call to the fake client for demo organizations (or when
 * ZOOM_DRIVER=fake) and to the HTTP client otherwise, so the public demo can
 * run on fixtures on the same server that serves real customers.
 */
class DelegatingZoomApi implements ZoomApi
{
    public function __construct(private readonly FakeZoomClient $fake, private readonly ZoomClient $http) {}

    private function for(ZoomConnection $connection): ZoomApi
    {
        if (config('zoom.driver') === 'fake') {
            return $this->fake;
        }

        return $connection->organization->setting('demo') ? $this->fake : $this->http;
    }

    private function tokenClient(): ZoomApi
    {
        return config('zoom.driver') === 'fake' ? $this->fake : $this->http;
    }

    public function exchangeCode(string $code, string $redirectUri): TokenSet
    {
        return $this->tokenClient()->exchangeCode($code, $redirectUri);
    }

    public function refreshToken(string $refreshToken): TokenSet
    {
        // Demo connections never expire (see DemoController), so this only reaches the HTTP client for real accounts.
        return $this->tokenClient()->refreshToken($refreshToken);
    }

    public function revokeToken(string $accessToken): void
    {
        $this->tokenClient()->revokeToken($accessToken);
    }

    public function me(ZoomConnection $connection): ZoomUser
    {
        return $this->for($connection)->me($connection);
    }

    public function getUser(ZoomConnection $connection, string $userId): ZoomUser
    {
        return $this->for($connection)->getUser($connection, $userId);
    }

    public function listUsers(ZoomConnection $connection, string $status): PagedList
    {
        return $this->for($connection)->listUsers($connection, $status);
    }

    public function userSummary(ZoomConnection $connection): UserSummary
    {
        return $this->for($connection)->userSummary($connection);
    }

    public function userFeatures(ZoomConnection $connection, string $userId): UserFeatures
    {
        return $this->for($connection)->userFeatures($connection, $userId);
    }

    public function updateUserType(ZoomConnection $connection, string $userId, int $type): ApiResult
    {
        return $this->for($connection)->updateUserType($connection, $userId, $type);
    }

    public function hostReport(ZoomConnection $connection, string $type, CarbonInterface $from, CarbonInterface $to): PagedList
    {
        return $this->for($connection)->hostReport($connection, $type, $from, $to);
    }

    public function planUsage(ZoomConnection $connection): PlanUsage
    {
        return $this->for($connection)->planUsage($connection);
    }

    public function upcomingMeetings(ZoomConnection $connection, string $userId): array
    {
        return $this->for($connection)->upcomingMeetings($connection, $userId);
    }
}
