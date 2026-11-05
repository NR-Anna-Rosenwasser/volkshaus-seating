<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('seating:reconcile')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('seating:send-links')->everyMinute()->withoutOverlapping();
Schedule::command('seating:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('seating:send-final')->hourly()->withoutOverlapping();
Schedule::command('seating:check-auto-assign')->everyFifteenMinutes()->withoutOverlapping();
