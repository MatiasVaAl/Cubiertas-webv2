<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Pricing\AreaPriceStrategy;
use App\Services\Pricing\PriceStrategy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PriceStrategy::class, AreaPriceStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-settings', fn (User $user) => $user->isAdministrator());
    }
}
