<?php

namespace App\Renewal;

use App\Models\Organization;
use App\Models\Scan;
use App\Support\Money;

/** The numbers behind the renewal card and reminder emails. */
class RenewalFigures
{
    /** @return array<string, mixed>|null null when there is no completed scan */
    public static function for(Organization $organization): ?array
    {
        $scan = Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->first();

        if ($scan === null) {
            return null;
        }

        $purchased = $scan->total('purchased') === null ? null : (int) $scan->total('purchased');
        $used = $scan->total('used') === null ? null : (int) $scan->total('used');
        $reclaimable = (int) $scan->total('reclaimable_seats');
        $target = $purchased === null ? null : max(0, $purchased - $reclaimable);
        $annual = $organization->annualSeatPriceCents();

        return [
            'purchased' => $purchased,
            'used' => $used,
            'reclaimable' => $reclaimable,
            'target' => $target,
            'target_money' => $target === null ? null : Money::forOrganization($organization, $target * $annual),
            'current_money' => $purchased === null ? null : Money::forOrganization($organization, $purchased * $annual),
            'savings_money' => $purchased === null ? null : Money::forOrganization($organization, $reclaimable * $annual),
            'days_left' => $organization->renewal_date ? (int) now()->startOfDay()->diffInDays($organization->renewal_date->startOfDay(), false) : null,
        ];
    }
}
