<?php

namespace App\Scan;

use Carbon\CarbonImmutable;

/**
 * Consecutive 30-day hosting windows counted back from "now": 0-30, 30-60, …
 * Each is ≤ 31 days so it fits one GET /report/users call (docs §5).
 */
final readonly class Window
{
    public const NONE = 'none';

    public const UNKNOWN = 'unknown';

    public const DAYS = 30;

    public function __construct(public int $index, public CarbonImmutable $from, public CarbonImmutable $to) {}

    public function label(): string
    {
        return sprintf('%d-%d', $this->index * self::DAYS, ($this->index + 1) * self::DAYS);
    }

    /** Days-ago at which this window starts (its most recent edge). */
    public function startDaysAgo(): int
    {
        return $this->index * self::DAYS;
    }

    /**
     * Windows needed to cover a lookback of $days (rounded up to whole windows).
     *
     * @return array<int, self>
     */
    public static function covering(int $days, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $count = max(1, (int) ceil($days / self::DAYS));
        $windows = [];

        for ($i = 0; $i < $count; $i++) {
            $to = $now->subDays($i * self::DAYS);
            $from = $now->subDays(($i + 1) * self::DAYS)->addDay();
            $windows[] = new self($i, $from->startOfDay(), $to->endOfDay());
        }

        return $windows;
    }

    /** @return array<int, string> */
    public static function labels(int $days): array
    {
        return array_map(fn (self $w) => $w->label(), self::covering($days));
    }

    /** Parse "90-120" → 90 (start days ago). */
    public static function startOf(string $label): ?int
    {
        if ($label === self::NONE || $label === self::UNKNOWN) {
            return null;
        }

        return (int) explode('-', $label)[0];
    }
}
