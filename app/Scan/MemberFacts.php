<?php

namespace App\Scan;

use App\Zoom\Data\ZoomUser;
use Carbon\CarbonImmutable;

/**
 * Everything the Classifier needs about one member, already fetched. Pure data:
 * the classifier never touches HTTP or the database.
 */
final readonly class MemberFacts
{
    /**
     * @param  array<string, int>  $meetingsByWindow  label => hosted meetings, e.g. ["0-30" => 2, "30-60" => 0]
     * @param  array<int, string>  $addOns  feature keys that are enabled (zoom_phone, webinar, …)
     */
    public function __construct(
        public string $status,
        public int $type,
        public bool $isOwnerOrAdmin = false,
        public bool $isRoom = false,
        public bool $hasBundle = false,
        public bool $bundleKnown = true,
        public array $addOns = [],
        public ?int $upcomingMeetings = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $lastLoginAt = null,
        public ?string $exclusionReason = null,
        public ?CarbonImmutable $excludedUntil = null,
        public bool $hostingKnown = true,
        public array $meetingsByWindow = [],
    ) {}

    public function isLicensed(): bool
    {
        return $this->type === ZoomUser::TYPE_LICENSED;
    }

    public function isDeactivated(): bool
    {
        return $this->status === ZoomUser::STATUS_INACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === ZoomUser::STATUS_PENDING;
    }
}
