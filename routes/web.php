<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->middleware('throttle:public')->name('home');

Route::get('/e/{event}', [PlanController::class, 'show'])->middleware('throttle:public')->name('plan');

Route::middleware('throttle:selection')->prefix('/s/{token}')->controller(SelectionController::class)->group(function () {
    Route::get('/', 'show')->name('selection.show');
    Route::get('/availability', 'availability')->name('selection.availability');
    Route::get('/suggestion', 'suggestion')->name('selection.suggestion');
    Route::post('/seats', 'save')->name('selection.save');
    Route::get('/ticket', 'ticket')->name('selection.ticket');
});
