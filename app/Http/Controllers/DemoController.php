<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Scan\ScanRunner;
use App\Tenancy\Tenancy;
use App\Zoom\FakeZoomClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public demo on fixture data (brief §5). Each visitor gets a throw-away
 * organization flagged demo=true, a fake Zoom connection and a completed scan,
 * and is signed in as its owner. Demo organizations are pruned after a day.
 */
class DemoController extends Controller
{
    public function enter(Request $request, Tenancy $tenancy, ScanRunner $runner): RedirectResponse
    {
        abort_unless(config('app.demo_enabled', true), 404);

        $organization = DB::transaction(function () use ($request): Organization {
            $user = User::query()->create([
                'name' => 'Demo Admin',
                'email' => 'demo+'.Str::lower(Str::random(12)).'@demo.seattrim.invalid',
                'password' => Str::random(40),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $organization = Organization::query()->create([
                'name' => 'Northwind School District (demo)',
                'timezone' => 'America/Chicago',
                'seat_price_cents' => 14900,
                'renewal_date' => CarbonImmutable::now()->addDays(52)->toDateString(),
                'billing_cycle' => 'annual',
                'settings' => ['demo' => true, 'currency' => 'USD', 'demo_ip' => $request->ip()],
            ]);
            $organization->addMember($user, Role::Owner);
            $user->switchToOrganization($organization);

            Auth::login($user);

            return $organization;
        });

        $tenancy->runAs($organization, function (Organization $organization) use ($runner): void {
            $connection = ZoomConnection::query()->create([
                'zoom_account_id' => 'demo-'.$organization->getKey(),
                'installer_zoom_user_id' => 'u01_danaowner',
                'installer_email' => 'dana.owner@example.edu',
                'access_token' => 'demo-access',
                'refresh_token' => 'demo-refresh',
                'expires_at' => now()->addYears(10),
                'scopes' => config('zoom.scopes'),
                'status' => ZoomConnection::STATUS_ACTIVE,
                'connected_at' => now(),
            ]);

            app(FakeZoomClient::class)->resetOverrides($connection);

            // Synchronous so the dashboard is populated on arrival (fixtures make this fast).
            $scan = Scan::query()->create(['trigger' => Scan::TRIGGER_ONBOARDING, 'status' => Scan::STATUS_QUEUED]);
            $runner->run($organization, $scan);
        });

        // Free demo of paid features: nothing is charged and nothing is real.
        onDemoPlan($organization);

        return redirect()->route('dashboard')->with('demo.entered', true);
    }
}
