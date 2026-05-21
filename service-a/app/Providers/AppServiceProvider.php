<?php

namespace App\Providers;

use App\Repositories\SalesOutlets\EloquentSalesOutletRepository;
use App\Repositories\SalesOutlets\SalesOutletRepositoryInterface;
use App\Services\SalesOutlets\SalesOutletService;
use App\Services\SalesOutlets\SalesOutletServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SalesOutletServiceInterface::class, SalesOutletService::class);
        $this->app->bind(SalesOutletRepositoryInterface::class, EloquentSalesOutletRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
