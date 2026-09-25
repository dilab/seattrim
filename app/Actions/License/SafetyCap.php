<?php

namespace App\Actions\License;

use App\Models\Scan;

/**
 * No single run may downgrade more than max(10, 10% of licensed seats) without a
 * second explicit confirmation (brief §7).
 */
class SafetyCap
{
    public static function limit(): int
    {
        $licensed = (int) (Scan::query()->where('status', Scan::STATUS_DONE)->latest('id')->first()?->total('licensed_total', 0) ?? 0);

        return max(10, (int) floor($licensed / 10));
    }

    public static function exceeds(int $count): bool
    {
        return $count > self::limit();
    }
}
