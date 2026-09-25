<?php

namespace App\Support;

use App\Billing\PlanResolver;
use App\Models\Organization;

/**
 * Paid-feature gate: current plan features, minus everything paid once the
 * seat-tier grace period has expired. Scans and the report are never gated.
 */
class Features
{
    public const BULK_ACTIONS = 'bulk_actions';

    public const AUTOMATION = 'automation';

    public const DIGEST = 'digest';

    public const RENEWAL_REMINDERS = 'renewal_reminders';

    public const CSV_EXPORT = 'csv_export';

    public const MULTIPLE_ADMINS = 'multiple_admins';

    public static function allows(Organization $organization, string $feature): bool
    {
        return PlanResolver::allows($organization, $feature);
    }

    public static function deniedMessage(string $feature): string
    {
        return match ($feature) {
            self::BULK_ACTIONS => 'Bulk actions are part of the paid plans. You can still downgrade or restore one user at a time.',
            self::AUTOMATION => 'Automation rules are part of the paid plans.',
            self::DIGEST => 'The weekly digest is part of the paid plans.',
            self::RENEWAL_REMINDERS => 'Renewal reminders are part of the paid plans.',
            self::CSV_EXPORT => 'CSV export is part of the paid plans.',
            self::MULTIPLE_ADMINS => 'Additional admins are part of the paid plans.',
            default => 'This feature is part of the paid plans.',
        };
    }
}
