<?php

namespace App\Http\Controllers\Zoom;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ZoomConnection;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Exceptions\ZoomApiException;
use App\Zoom\FakeZoomClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin-managed General app OAuth (docs/zoom-api-notes.md §2).
 * /zoom/connect  → zoom.us/oauth/authorize (with a per-attempt state)
 * /zoom/callback → exchange code, identify account, store encrypted tokens.
 */
class OAuthController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy, private readonly ZoomApi $zoom) {}

    public function connect(Request $request): RedirectResponse
    {
        $organization = $this->tenancy->currentOrFail();
        Gate::authorize('update', $organization);

        $state = Str::random(40);
        $request->session()->put('zoom.oauth', ['state' => $state, 'organization_id' => $organization->getKey()]);

        if (config('zoom.driver') === 'fake') {
            return redirect()->route('zoom.callback', ['code' => 'fake-code', 'state' => $state]);
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('zoom.client_id'),
            'redirect_uri' => config('zoom.redirect_uri'),
            'state' => $state,
        ]);

        return redirect()->away(rtrim((string) config('zoom.oauth_base_url'), '/').'/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $organization = $this->tenancy->currentOrFail();
        Gate::authorize('update', $organization);

        $expected = $request->session()->pull('zoom.oauth');

        if (! is_array($expected)
            || ! hash_equals((string) ($expected['state'] ?? ''), (string) $request->query('state', ''))
            || (int) ($expected['organization_id'] ?? 0) !== (int) $organization->getKey()) {
            abort(403, 'The Zoom authorization state did not match. Please start the connection again.');
        }

        if ($request->filled('error')) {
            return redirect()->route('connection.edit')->with('zoom.error', 'Zoom did not grant access: '.$request->query('error_description', $request->query('error')));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('connection.edit')->with('zoom.error', 'Zoom did not return an authorization code.');
        }

        try {
            $tokens = $this->zoom->exchangeCode($code, (string) config('zoom.redirect_uri'));

            $connection = DB::transaction(function () use ($organization, $tokens): ZoomConnection {
                $connection = ZoomConnection::query()->firstOrNew(['organization_id' => $organization->getKey()]);
                $connection->fill([
                    'zoom_account_id' => $connection->zoom_account_id ?: 'pending',
                    'connected_at' => now(),
                    'revoked_at' => null,
                ]);
                $connection->storeTokens($tokens);

                return $connection;
            });

            $me = $this->zoom->me($connection);
            $accountId = (string) ($me->raw['account_id'] ?? '');

            if ($accountId === '' && $this->zoom instanceof FakeZoomClient) {
                $accountId = 'demo-'.$organization->getKey();
            }

            if ($accountId === '') {
                throw new ZoomApiException('GET /users/me did not include account_id.', 200);
            }

            $connection->forceFill([
                'zoom_account_id' => $accountId,
                'installer_zoom_user_id' => $me->id,
                'installer_email' => $me->email,
            ])->save();

            $organization->setSetting('zoom.disconnected_at', null);
            $organization->setSetting('zoom.disconnect_reason', null);
            $organization->save();
        } catch (ZoomApiException $e) {
            Log::warning('zoom.oauth.failed', ['organization' => $organization->getKey(), 'error' => $e->summary()]);

            return redirect()->route('connection.edit')->with('zoom.error', 'Connecting to Zoom failed: '.$e->summary());
        }

        Log::info('zoom.oauth.connected', ['organization' => $organization->getKey(), 'connection' => $connection->describe(), 'missing_scopes' => $connection->missingScopes()]);

        $hasScan = Scan::query()->where('status', Scan::STATUS_DONE)->exists();

        return redirect()->route($hasScan ? 'connection.edit' : 'onboarding')->with('zoom.connected', true);
    }
}
