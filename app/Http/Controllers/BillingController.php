<?php

namespace App\Http\Controllers;

use App\Billing\Plans;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Cashier\Checkout;
use Symfony\Component\HttpFoundation\Response;

/** Stripe Checkout and the Billing Portal, both through Cashier. Owner only. */
class BillingController extends Controller
{
    public function checkout(Request $request, Tenancy $tenancy, string $tier): Response|Checkout
    {
        $organization = $tenancy->currentOrFail();
        Gate::authorize('manageBilling', $organization);

        $plan = Plans::find($tier);
        abort_if($plan === null || $plan->isFree() || $plan->stripePrice === null, 404);

        if (config('cashier.key') === null || config('cashier.secret') === null) {
            return redirect()->route('billing')->with('billing.error', 'Stripe is not configured on this server.');
        }

        if ($organization->subscribed('default')) {
            $organization->subscription('default')->swap($plan->stripePrice);

            return redirect()->route('billing')->with('billing.success', "Switched to {$plan->name}.");
        }

        return $organization
            ->newSubscription('default', $plan->stripePrice)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing', ['checkout' => 'success']),
                'cancel_url' => route('billing', ['checkout' => 'cancelled']),
            ]);
    }

    public function portal(Tenancy $tenancy): RedirectResponse
    {
        $organization = $tenancy->currentOrFail();
        Gate::authorize('manageBilling', $organization);

        if (! $organization->hasStripeId()) {
            return redirect()->route('billing')->with('billing.error', 'No billing account yet. Subscribe to a plan first.');
        }

        return $organization->redirectToBillingPortal(route('billing'));
    }
}
