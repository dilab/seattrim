<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Notifications\RenewalReminder;
use App\Renewal\RenewalFigures;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Runs hourly; at 09:00 local sends the 60/30/7-day reminder once per renewal date. */
class SendRenewalReminders extends Command
{
    public const MILESTONES = [60, 30, 7];

    protected $signature = 'seattrim:send-renewal-reminders {--force : Ignore the local hour}';

    protected $description = 'Email owners and admins 60, 30 and 7 days before the Zoom renewal date';

    public function handle(Tenancy $tenancy): int
    {
        $sent = 0;

        foreach (Organization::query()->whereNotNull('renewal_date')->cursor() as $organization) {
            if (! Features::allows($organization, Features::RENEWAL_REMINDERS)) {
                continue;
            }

            $local = CarbonImmutable::now()->setTimezone($organization->timezone ?: 'UTC');
            if (! $this->option('force') && $local->hour !== 9) {
                continue;
            }

            $renewal = $organization->renewal_date;
            if ($renewal === null) {
                continue;
            }
            $daysLeft = (int) $local->startOfDay()->diffInDays($renewal->toImmutable()->setTimezone($local->timezone)->startOfDay(), false);

            // Fire the closest milestone that is ≥ daysLeft and not yet sent, so a reminder is never
            // skipped when the cron misses an hour or the date was entered late.
            $milestone = null;
            foreach (self::MILESTONES as $m) {
                if ($daysLeft <= $m && $daysLeft >= 0) {
                    $milestone = $m;
                }
            }
            if ($milestone === null) {
                continue;
            }

            $key = 'renewal.sent.'.$renewal->toDateString().'.'.$milestone;
            if ($organization->setting($key)) {
                continue;
            }

            $figures = $tenancy->runAs($organization, fn () => RenewalFigures::for($organization));
            if ($figures === null) {
                continue;
            }

            Notification::send(
                $organization->users()->wherePivotIn('role', ['owner', 'admin'])->get(),
                new RenewalReminder($organization, $daysLeft, $figures),
            );

            foreach (self::MILESTONES as $m) {
                if ($m >= $milestone) {
                    $organization->setSetting('renewal.sent.'.$renewal->toDateString().'.'.$m, $local->toIso8601String());
                }
            }
            $organization->save();
            $sent++;
        }

        $this->info("Sent {$sent} renewal reminder(s).");

        return self::SUCCESS;
    }
}
