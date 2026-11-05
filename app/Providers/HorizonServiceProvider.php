<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Without Horizon's exception for local environments: a server left on
     * APP_ENV=local must not show job data to anyone.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Horizon shows job payloads with email addresses, so it takes the admin
     * login. Users exist only through AdminUserSeeder.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user !== null);
    }
}
