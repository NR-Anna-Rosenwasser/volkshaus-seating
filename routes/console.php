<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('seating:heartbeat')->everyMinute();
Schedule::command('seating:reconcile')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('seating:send-links')->everyMinute()->withoutOverlapping();
Schedule::command('seating:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('seating:send-final')->hourly()->withoutOverlapping();
Schedule::command('seating:check-auto-assign')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('seating:backup')->dailyAt('03:00')->withoutOverlapping();
// After the backup, so a wrong EVENT_DATE can still be recovered from last night's dump.
Schedule::command('seating:purge')->dailyAt('04:00')->withoutOverlapping();
