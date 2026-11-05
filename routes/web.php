<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\SelectionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/plan');

Route::get('/plan', [PlanController::class, 'show'])->name('plan');

Route::middleware('throttle:selection')->prefix('/s/{token}')->controller(SelectionController::class)->group(function () {
    Route::get('/', 'show')->name('selection.show');
    Route::get('/availability', 'availability')->name('selection.availability');
    Route::get('/suggestion', 'suggestion')->name('selection.suggestion');
    Route::post('/seats', 'save')->name('selection.save');
    Route::get('/ticket', 'ticket')->name('selection.ticket');
});
