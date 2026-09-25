<?php

namespace App\Console\Commands;

use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomConnection;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Runs hourly. Starts the daily scan for every connected organization whose
 * local time is 03:00 and that has not been scanned by schedule today.
 */
class DispatchScheduledScans extends Command
{
    protected $signature = 'seattrim:dispatch-scheduled-scans {--hour=3 : Local hour at which scans run}';

    protected $description = 'Queue the daily scan for organizations whose local time matches the scan hour';

    public function handle(): int
    {
        $hour = (int) $this->option('hour');
        $started = 0;

        $connectedIds = ZoomConnection::query()->allOrganizations()->where('status', ZoomConnection::STATUS_ACTIVE)->pluck('organization_id');

        foreach (Organization::query()->whereIn('id', $connectedIds)->cursor() as $organization) {
            $local = CarbonImmutable::now()->setTimezone($organization->timezone ?: 'UTC');

            if ($local->hour !== $hour) {
                continue;
            }

            if ($organization->setting('scan.last_scheduled_date') === $local->toDateString()) {
                continue;
            }

            $organization->setSetting('scan.last_scheduled_date', $local->toDateString());
            $organization->save();

            ScanOrganization::start($organization, Scan::TRIGGER_SCHEDULED);
            $started++;
        }

        $this->info("Queued {$started} scheduled scan(s).");

        return self::SUCCESS;
    }
}
