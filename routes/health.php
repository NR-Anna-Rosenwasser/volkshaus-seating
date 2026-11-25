<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Its own group without a session: a monitor would otherwise leave a session row per check.
Route::get('/health', HealthController::class)->middleware('throttle:public')->name('health');
