<?php

/*
|--------------------------------------------------------------------------
| Zoom integration
|--------------------------------------------------------------------------
|
| Every value here that describes the Zoom API (paths, scope names, limits)
| was verified against developers.zoom.us on 2026-09-25. The verification
| record, with doc URLs, lives in docs/zoom-api-notes.md. Change that file
| when you change this one.
|
*/

return [

    // "http" talks to api.zoom.us through App\Zoom\ZoomClient.
    // "fake" binds App\Zoom\FakeZoomClient (fixtures in tests/Fixtures/zoom).
    'driver' => env('ZOOM_DRIVER', 'http'),

    'client_id' => env('ZOOM_CLIENT_ID'),
    'client_secret' => env('ZOOM_CLIENT_SECRET'),

    // "Secret Token" from the app's Features > Access page. Used for the
    // endpoint.url_validation CRC challenge and for x-zm-signature checks.
    'webhook_secret_token' => env('ZOOM_WEBHOOK_SECRET_TOKEN'),

    'redirect_uri' => env('ZOOM_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/zoom/callback'),

    'oauth_base_url' => 'https://zoom.us/oauth',
    'api_base_url' => 'https://api.zoom.us/v2',

    // Granular scopes requested at install time. Least privilege: each one
    // maps to exactly one capability in docs/zoom-api-notes.md.
    'scopes' => [
        'user:read:list_users:admin',     // GET /users (all statuses)
        'user:read:user:admin',           // GET /users/{userId}
        'user:read:settings:admin',       // GET /users/{userId}/settings -> feature.* add-on flags
        'user:update:user:admin',         // PATCH /users/{userId} type 2 -> 1 and back
        'report:read:list_users:admin',   // GET /report/users (active/inactive hosts)
        'billing:read:plan_usage:admin',  // GET /accounts/me/plans/usage (purchased vs used seats)
        'meeting:read:list_meetings:admin', // GET /users/{userId}/meetings?type=upcoming
    ],

    // Maximum page sizes we send. Verified per endpoint in docs/zoom-api-notes.md.
    'page_size' => [
        'users' => 300,
        'report_users' => 300,
        'meetings' => 300,
    ],

    // GET /report/users accepts at most one month per request and holds
    // roughly six months of data. We split lookback into <= 30-day windows.
    'report_window_days' => 30,
    'report_max_lookback_days' => 180,

    // Refresh the access token when fewer than this many minutes remain.
    'token_refresh_leeway_minutes' => 5,

    // Refresh tokens expire after 90 days of non-use (OAuth doc). The daily
    // scan keeps them fresh; this is only used for UI warnings.
    'refresh_token_lifetime_days' => 90,

];
