<?php

namespace App\Providers;

use App\Support\VenueLayout;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(VenueLayout::class, fn () => new VenueLayout(config('seating.seat_data_path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
