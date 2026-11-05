<?php

use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/plan');

Route::get('/plan', [PlanController::class, 'show'])->name('plan');
