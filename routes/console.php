<?php

use Illuminate\Support\Facades\Schedule;

// Daily scans at 03:00 in each organization's timezone (checked hourly). Scheduled scans
// trigger the automation rule when they finish.
Schedule::command('seattrim:dispatch-scheduled-scans')->hourly()->withoutOverlapping();

// Weekly digest on Monday 08:00 local time (checked hourly).
Schedule::command('seattrim:send-weekly-digests')->hourly()->withoutOverlapping();

// Renewal reminders 60/30/7 days before the Zoom renewal date, 09:00 local (checked hourly).
Schedule::command('seattrim:send-renewal-reminders')->hourly()->withoutOverlapping();

// Public demo organizations live for a day.
Schedule::command('seattrim:prune-demo')->hourly();
