<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's current organization into Tenancy.
 * Users without one are sent to the create-organization step.
 *
 * The tenant is cleared in terminate(), not after $next(): Livewire replays this
 * middleware on /livewire/update before running the component action, so clearing
 * it on the way out of handle() would leave the action without an organization.
 */
class EnsureCurrentOrganization
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $organization = $user->currentOrganization;

        if ($organization === null || ! $user->belongsToOrganization($organization)) {
            $organization = $user->organizations()->orderBy('organization_user.created_at')->first();

            if ($organization instanceof Organization) {
                $user->switchToOrganization($organization);
            }
        }

        if ($organization === null) {
            return redirect()->route('organizations.create');
        }

        $this->tenancy->set($organization);

        return $next($request);
    }

    public function terminate(): void
    {
        $this->tenancy->forget();
    }
}
