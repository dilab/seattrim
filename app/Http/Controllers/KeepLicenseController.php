<?php

namespace App\Http\Controllers;

use App\Automation\AutomationRunner;
use App\Models\DowngradeNotice;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * "Keep my license" from the warning email. The link is signed and expiring
 * (no login). GET shows a confirmation page so link scanners cannot trigger
 * the change; POST performs it.
 */
class KeepLicenseController extends Controller
{
    public function show(int $notice, Tenancy $tenancy): View
    {
        $notice = DowngradeNotice::query()->allOrganizations()->with(['member', 'organization'])->findOrFail($notice);

        return view('public.keep-license', [
            'notice' => $notice,
            'organization' => $notice->organization,
            'member' => $notice->member,
            'alreadyKept' => $notice->kept_at !== null,
            'closed' => ! $notice->isOpen() && $notice->kept_at === null,
        ]);
    }

    public function store(int $notice, Tenancy $tenancy): RedirectResponse
    {
        $notice = DowngradeNotice::query()->allOrganizations()->with(['member', 'organization'])->findOrFail($notice);

        $tenancy->runAs($notice->organization, fn () => AutomationRunner::keep($notice));

        return redirect()->route('keep-license', ['notice' => $notice->id, 'signature' => request('signature'), 'expires' => request('expires')])
            ->with('kept', true);
    }
}
