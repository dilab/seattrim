<?php

namespace App\Automation;

use App\Enums\Bucket;
use App\Models\DowngradeNotice;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\Scan;
use App\Support\Money;
use Carbon\CarbonImmutable;

class DigestBuilder
{
    /** @return array<string, mixed> */
    public static function build(Organization $organization, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(7);
        $scan = Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->first();

        $actions = LicenseAction::query()->where('created_at', '>=', $since)->where('action', LicenseAction::ACTION_DOWNGRADE);

        $buckets = [];
        foreach ([Bucket::PendingLicensed, Bucket::IdleLicensed, Bucket::DeactivatedLicensed, Bucket::Protected] as $bucket) {
            $count = (int) ($scan?->total("buckets.{$bucket->value}.count") ?? 0);
            if ($bucket === Bucket::DeactivatedLicensed && $count === 0) {
                continue;
            }
            $buckets[] = [
                'label' => $bucket->label(),
                'count' => $count,
                'money' => Money::forOrganization($organization, (int) ($scan?->total("buckets.{$bucket->value}.annual_cents") ?? 0)),
            ];
        }

        $unassigned = $scan?->total('unassigned.known')
            ? ['count' => (int) $scan->total('unassigned.count'), 'money' => Money::forOrganization($organization, (int) $scan->total('unassigned.annual_cents'))]
            : null;

        $renewal = null;
        if ($organization->renewal_date && $scan) {
            $daysLeft = (int) $now->diffInDays($organization->renewal_date, false);
            $purchased = $scan->total('purchased') === null ? null : (int) $scan->total('purchased');
            $reclaimable = (int) $scan->total('reclaimable_seats');
            $renewal = $purchased !== null
                ? sprintf('%d days to go. You pay for %d seats, %d are reclaimable: reduce to %d seats (%s/yr).', $daysLeft, $purchased, $reclaimable, max(0, $purchased - $reclaimable), Money::forOrganization($organization, max(0, $purchased - $reclaimable) * $organization->annualSeatPriceCents()))
                : sprintf('%d days to go. %d seats are reclaimable; check the purchased quantity in Zoom Billing.', $daysLeft, $reclaimable);
        }

        return [
            'downgraded' => (clone $actions)->where('status', LicenseAction::STATUS_DONE)->count(),
            'skipped' => (clone $actions)->whereIn('status', [LicenseAction::STATUS_SKIPPED, LicenseAction::STATUS_FAILED])->where('dry_run', false)->count(),
            'dry_run_would' => (clone $actions)->where('dry_run', true)->where('reason', 'like', 'Dry run%')->count(),
            'kept' => DowngradeNotice::query()->where('kept_at', '>=', $since)->count(),
            'pending' => DowngradeNotice::query()->open()->count(),
            'buckets' => $buckets,
            'unassigned' => $unassigned,
            'renewal' => $renewal,
            'waste_annual' => Money::forOrganization($organization, (int) ($scan?->total('waste_annual_cents') ?? 0)),
        ];
    }
}
