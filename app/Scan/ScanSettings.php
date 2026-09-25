<?php

namespace App\Scan;

use App\Models\Organization;

/** Per-organization knobs the classifier needs. Stored in organizations.settings. */
final readonly class ScanSettings
{
    public const THRESHOLD_CHOICES = [30, 60, 90, 180];

    public const DEFAULT_THRESHOLD = 90;

    public const DEFAULT_NEW_HIRE_DAYS = 30;

    public function __construct(
        public int $thresholdDays = self::DEFAULT_THRESHOLD,
        public int $newHireDays = self::DEFAULT_NEW_HIRE_DAYS,
        public int $maxLookbackDays = 180,
    ) {}

    public static function for(Organization $organization): self
    {
        $threshold = (int) $organization->setting('scan.threshold_days', self::DEFAULT_THRESHOLD);

        return new self(
            thresholdDays: in_array($threshold, self::THRESHOLD_CHOICES, true) ? $threshold : self::DEFAULT_THRESHOLD,
            newHireDays: max(0, (int) $organization->setting('scan.new_hire_days', self::DEFAULT_NEW_HIRE_DAYS)),
            maxLookbackDays: (int) config('zoom.report_max_lookback_days', 180),
        );
    }

    /** How far back we must pull host reports: the threshold, but never past what Zoom keeps (~180 days). */
    public function lookbackDays(): int
    {
        return min(max($this->thresholdDays, 90), $this->maxLookbackDays);
    }
}
