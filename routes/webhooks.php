<?php

use App\Http\Controllers\HieventsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hievents', HieventsWebhookController::class)->name('webhooks.hievents');
