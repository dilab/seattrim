<?php

namespace App\Scan;

use App\Enums\Bucket;
use App\Models\Organization;
use App\Models\ZoomMember;
use App\Models\ZoomSeatsSnapshot;

/**
 * Counts and dollars per bucket (brief §6.6). Annual figures use the org's
 * annualised seat price; monthly plans also get a monthly figure.
 */
class Totals
{
    /** @return array<string, mixed> */
    public static function compute(Organization $organization, ?ZoomSeatsSnapshot $snapshot): array
    {
        $annualSeat = $organization->annualSeatPriceCents();
        $monthly = $organization->billing_cycle === 'monthly';

        $buckets = [];
        foreach (Bucket::cases() as $bucket) {
            $count = ZoomMember::query()->present()->where('bucket', $bucket->value)->count();
            $buckets[$bucket->value] = self::money($count, $annualSeat, $monthly, $bucket->isWaste());
        }

        $unassigned = $snapshot?->unassigned_seats;
        $licensedTotal = ZoomMember::query()->present()->licensed()->count();
        $eligible = ZoomMember::query()->present()->where('eligible_for_downgrade', true)->count();

        $wasteSeats = ($unassigned ?? 0)
            + $buckets[Bucket::DeactivatedLicensed->value]['count']
            + $buckets[Bucket::PendingLicensed->value]['count']
            + $buckets[Bucket::IdleLicensed->value]['count'];

        return [
            'buckets' => $buckets,
            'unassigned' => $unassigned === null
                ? ['count' => null, 'annual_cents' => null, 'monthly_cents' => null, 'known' => false]
                : self::money($unassigned, $annualSeat, $monthly, true) + ['known' => true],
            'licensed_total' => $licensedTotal,
            'eligible_for_downgrade' => $eligible,
            'purchased' => $snapshot?->purchased_seats,
            'used' => $snapshot?->used_seats,
            'seat_source' => $snapshot?->source,
            'reclaimable_seats' => $wasteSeats,
            'waste_annual_cents' => $wasteSeats * $annualSeat,
            'waste_monthly_cents' => $monthly ? $wasteSeats * $organization->seat_price_cents : null,
            'seat_price_cents' => $organization->seat_price_cents,
            'billing_cycle' => $organization->billing_cycle,
            'members_total' => ZoomMember::query()->present()->count(),
        ];
    }

    /** @return array{count: int, annual_cents: int, monthly_cents: int|null} */
    private static function money(int $count, int $annualSeat, bool $monthly, bool $waste): array
    {
        return [
            'count' => $count,
            'annual_cents' => $waste ? $count * $annualSeat : 0,
            'monthly_cents' => $waste && $monthly ? (int) round($count * $annualSeat / 12) : null,
        ];
    }
}
