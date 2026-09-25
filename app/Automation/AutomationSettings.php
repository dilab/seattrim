<?php

namespace App\Automation;

use App\Enums\Bucket;
use App\Models\Organization;
use App\Scan\ScanSettings;

/**
 * Per-organization automation rule (brief §8). Off by default; dry-run on by
 * default when first enabled. Stored in organizations.settings['automation'].
 */
final readonly class AutomationSettings
{
    public const DEFAULT_WARNING_DAYS = 7;

    public const KEEP_DAYS = 90;

    /** @param array<int, string> $buckets */
    public function __construct(
        public bool $enabled = false,
        public bool $dryRun = true,
        public int $warningDays = self::DEFAULT_WARNING_DAYS,
        public array $buckets = [Bucket::PendingLicensed->value, Bucket::IdleLicensed->value],
        public bool $weeklyDigest = true,
        public ?string $replyTo = null,
    ) {}

    public static function for(Organization $organization): self
    {
        $raw = (array) $organization->setting('automation', []);
        $buckets = array_values(array_intersect((array) ($raw['buckets'] ?? [Bucket::PendingLicensed->value, Bucket::IdleLicensed->value]), self::allowedBuckets()));

        return new self(
            enabled: (bool) ($raw['enabled'] ?? false),
            dryRun: (bool) ($raw['dry_run'] ?? true),
            warningDays: max(1, min(60, (int) ($raw['warning_days'] ?? self::DEFAULT_WARNING_DAYS))),
            buckets: $buckets,
            weeklyDigest: (bool) ($raw['weekly_digest'] ?? true),
            replyTo: isset($raw['reply_to']) && $raw['reply_to'] !== '' ? (string) $raw['reply_to'] : null,
        );
    }

    /** @return array<int, string> */
    public static function allowedBuckets(): array
    {
        return [Bucket::PendingLicensed->value, Bucket::IdleLicensed->value];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'dry_run' => $this->dryRun,
            'warning_days' => $this->warningDays,
            'buckets' => $this->buckets,
            'weekly_digest' => $this->weeklyDigest,
            'reply_to' => $this->replyTo,
        ];
    }

    public function thresholdDays(Organization $organization): int
    {
        return ScanSettings::for($organization)->thresholdDays;
    }
}
