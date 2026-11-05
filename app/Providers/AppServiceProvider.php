<?php

namespace App\Providers;

use App\Models\Order;
use App\Services\AdminAlert;
use App\Support\VenueLayout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        Route::bind('token', fn (string $token): Order => Order::where('token_hash', hash('sha256', $token))->firstOrFail());

        Queue::failing(function (JobFailed $event): void {
            if ($event->job->resolveName() === SendQueuedMailable::class) {
                app(AdminAlert::class)->mailFailed();
            }
        });

        // Generous: an open map polls every 10 seconds, and a whole group can share one IP.
        RateLimiter::for('selection', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
