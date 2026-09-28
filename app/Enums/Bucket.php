<?php

namespace App\Enums;

enum Bucket: string
{
    case DeactivatedLicensed = 'deactivated_licensed';
    case PendingLicensed = 'pending_licensed';
    case IdleLicensed = 'idle_licensed';
    case Protected = 'protected';
    case Healthy = 'healthy';

    public function label(): string
    {
        return match ($this) {
            self::DeactivatedLicensed => 'Deactivated, still licensed (should be rare)',
            self::PendingLicensed => 'Pending invite, licensed',
            self::IdleLicensed => 'Idle licensed',
            self::Protected => 'Protected',
            self::Healthy => 'Healthy',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DeactivatedLicensed => 'Zoom normally removes licenses on deactivation. This user still shows as Licensed, so the seat is held.',
            self::PendingLicensed => 'The invitation was never accepted, yet a Licensed seat is reserved for it.',
            self::IdleLicensed => 'Active and licensed, but has not hosted a meeting within your threshold.',
            self::Protected => 'Would be idle, but a guardrail (bundle, add-on, role, upcoming meeting, exclusion or new hire) stops a downgrade.',
            self::Healthy => 'Basic users, and licensed users who host meetings.',
        };
    }

    /** Buckets whose seats count as reclaimable waste. */
    public function isWaste(): bool
    {
        return in_array($this, [self::DeactivatedLicensed, self::PendingLicensed, self::IdleLicensed], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::DeactivatedLicensed => 'red',
            self::PendingLicensed => 'orange',
            self::IdleLicensed => 'amber',
            self::Protected => 'blue',
            self::Healthy => 'green',
        };
    }
}
