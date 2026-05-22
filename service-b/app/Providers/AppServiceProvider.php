<?php

namespace App\Providers;

use App\Repositories\SalesOutlets\EloquentSalesOutletsDataRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsExportRepository;
use App\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepository;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportRepositoryInterface;
use App\Services\SalesOutlets\SalesOutletsExportService;
use App\Services\SalesOutlets\SalesOutletsExportServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SalesOutletsExportServiceInterface::class, SalesOutletsExportService::class);
        $this->app->bind(SalesOutletsExportRepositoryInterface::class, EloquentSalesOutletsExportRepository::class);
        $this->app->bind(SalesOutletsDataRepositoryInterface::class, EloquentSalesOutletsDataRepository::class);
        $this->app->bind(SalesOutletsExportMetadataRepositoryInterface::class, SalesOutletsExportMetadataRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
