<?php

use Illuminate\Support\Facades\Schedule;

// Daily scans at 03:00 in each organization's timezone (checked hourly).
Schedule::command('seattrim:dispatch-scheduled-scans')->hourly()->withoutOverlapping();
