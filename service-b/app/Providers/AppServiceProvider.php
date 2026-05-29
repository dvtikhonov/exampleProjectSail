<?php

namespace App\Providers;

use App\Contracts\SalesOutlets\ExportFileStorageInterface;
use App\Contracts\SalesOutlets\ExportPathNamingInterface;
use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Contracts\SalesOutlets\SalesOutletsCsvWriterInterface;
use App\Repositories\SalesOutlets\EloquentSalesOutletsDataRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsExportRepository;
use App\Repositories\SalesOutlets\EloquentSalesOutletsMailRepository;
use App\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepository;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsMailRepositoryInterface;
use App\Services\SalesOutlets\ExportPathNaming;
use App\Services\SalesOutlets\LaravelReportMailSender;
use App\Services\SalesOutlets\LocalExportFileStorage;
use App\Services\SalesOutlets\SalesOutletsCsvWriter;
use App\Services\SalesOutlets\SalesOutletsExportApiServiceInterface;
use App\Services\SalesOutlets\SalesOutletsExportService;
use App\Services\SalesOutlets\SalesOutletsExportWorkerServiceInterface;
use App\Services\SalesOutlets\SalesOutletsMailApiServiceInterface;
use App\Services\SalesOutlets\SalesOutletsMailService;
use App\Services\SalesOutlets\SalesOutletsMailWorkerServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SalesOutletsExportApiServiceInterface::class, SalesOutletsExportService::class);
        $this->app->bind(SalesOutletsExportWorkerServiceInterface::class, SalesOutletsExportService::class);
        $this->app->bind(SalesOutletsMailApiServiceInterface::class, SalesOutletsMailService::class);
        $this->app->bind(SalesOutletsMailWorkerServiceInterface::class, SalesOutletsMailService::class);
        $this->app->bind(SalesOutletsExportRepositoryInterface::class, EloquentSalesOutletsExportRepository::class);
        $this->app->bind(SalesOutletsMailRepositoryInterface::class, EloquentSalesOutletsMailRepository::class);
        $this->app->bind(SalesOutletsDataRepositoryInterface::class, EloquentSalesOutletsDataRepository::class);
        $this->app->bind(SalesOutletsExportMetadataRepositoryInterface::class, SalesOutletsExportMetadataRepository::class);
        $this->app->bind(ExportFileStorageInterface::class, LocalExportFileStorage::class);
        $this->app->bind(ReportMailSenderInterface::class, LaravelReportMailSender::class);
        $this->app->bind(SalesOutletsCsvWriterInterface::class, SalesOutletsCsvWriter::class);
        $this->app->bind(ExportPathNamingInterface::class, ExportPathNaming::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
