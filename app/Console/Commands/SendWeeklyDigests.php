<?php

namespace App\Console\Commands;

use App\Automation\AutomationSettings;
use App\Automation\DigestBuilder;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Notifications\WeeklyDigest;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Runs hourly; sends each organization's digest on Monday at 08:00 local time, once per week. */
class SendWeeklyDigests extends Command
{
    protected $signature = 'seattrim:send-weekly-digests {--force : Send now regardless of time} {--organization= : Only this organization id}';

    protected $description = 'Send the weekly license digest to organization owners and admins';

    public function handle(Tenancy $tenancy): int
    {
        $sent = 0;
        $connectedIds = ZoomConnection::query()->allOrganizations()->where('status', ZoomConnection::STATUS_ACTIVE)->pluck('organization_id');
        $query = Organization::query()->whereIn('id', $connectedIds);

        if ($this->option('organization')) {
            $query->whereKey((int) $this->option('organization'));
        }

        foreach ($query->cursor() as $organization) {
            $settings = AutomationSettings::for($organization);
            if (! $settings->weeklyDigest || ! Features::allows($organization, Features::DIGEST)) {
                continue;
            }

            $local = CarbonImmutable::now()->setTimezone($organization->timezone ?: 'UTC');
            $week = $local->format('o-W');

            if (! $this->option('force')) {
                if (! $local->isMonday() || $local->hour !== 8 || $organization->setting('digest.last_week') === $week) {
                    continue;
                }
            }

            $data = $tenancy->runAs($organization, fn () => DigestBuilder::build($organization));
            $recipients = $organization->users()->wherePivotIn('role', ['owner', 'admin'])->get();
            Notification::send($recipients, new WeeklyDigest($organization, $data));

            $organization->setSetting('digest.last_week', $week);
            $organization->save();
            $sent++;
        }

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
