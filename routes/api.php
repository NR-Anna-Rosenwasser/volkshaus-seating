<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/e/{event}/plan', [PlanController::class, 'data'])->middleware('throttle:public')->name('api.plan');
