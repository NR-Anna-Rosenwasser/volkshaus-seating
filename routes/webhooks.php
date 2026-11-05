<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\HieventsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hievents', HieventsWebhookController::class)->middleware('throttle:webhook')->name('webhooks.hievents');

// Here because this group has no session: a monitor would otherwise leave a session row per check.
Route::get('/health', HealthController::class)->middleware('throttle:public')->name('health');
