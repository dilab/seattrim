<?php

use Illuminate\Support\Facades\Schedule;

// Daily scans at 03:00 in each organization's timezone (checked hourly). Scheduled scans
// trigger the automation rule when they finish.
Schedule::command('seattrim:dispatch-scheduled-scans')->hourly()->withoutOverlapping();

// Weekly digest on Monday 08:00 local time (checked hourly).
Schedule::command('seattrim:send-weekly-digests')->hourly()->withoutOverlapping();
