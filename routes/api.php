<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/plan', [PlanController::class, 'data'])->middleware('throttle:public')->name('api.plan');
