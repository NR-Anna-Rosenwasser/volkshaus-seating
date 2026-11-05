<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('seating:reconcile')->everyThirtyMinutes()->withoutOverlapping();
