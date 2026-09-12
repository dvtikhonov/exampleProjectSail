<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Providers;

use App\Modules\FoodReport\Console\BackfillFoodOrderItemsCommand;
use App\Modules\FoodReport\Contracts\FoodOrderConfirmedBackfillSourceInterface;
use App\Modules\FoodReport\Contracts\FoodOrderItemSyncServiceInterface;
use App\Modules\FoodReport\Contracts\FoodOrderItemWriteRepositoryInterface;
use App\Modules\FoodReport\Contracts\FoodOrderReportRepositoryInterface;
use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use App\Modules\FoodReport\Contracts\FoodReportQueryServiceInterface;
use App\Modules\FoodReport\Contracts\FoodReportSpreadsheetExporterInterface;
use App\Modules\FoodReport\Repositories\EloquentFoodOrderConfirmedBackfillSource;
use App\Modules\FoodReport\Repositories\EloquentFoodOrderItemWriteRepository;
use App\Modules\FoodReport\Repositories\EloquentFoodOrderReportRepository;
use App\Modules\FoodReport\Services\FoodOrderItemSyncService;
use App\Modules\FoodReport\Services\FoodReportMaxDeliveryService;
use App\Modules\FoodReport\Services\FoodReportQueryService;
use App\Modules\FoodReport\Services\PhpSpreadsheetFoodReportExporter;
use Illuminate\Support\ServiceProvider;

/**
 * DI-привязки модуля отчётов Food (FoodReport, вариант B).
 */
class FoodReportServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и реализации модуля FoodReport.
     */
    public function register(): void
    {
        $this->app->bind(
            FoodOrderItemWriteRepositoryInterface::class,
            EloquentFoodOrderItemWriteRepository::class,
        );
        $this->app->bind(
            FoodOrderConfirmedBackfillSourceInterface::class,
            EloquentFoodOrderConfirmedBackfillSource::class,
        );
        $this->app->singleton(FoodOrderItemSyncService::class);
        $this->app->bind(
            FoodOrderItemSyncServiceInterface::class,
            static fn ($app): FoodOrderItemSyncService => $app->make(FoodOrderItemSyncService::class),
        );
        $this->app->bind(
            FoodOrderReportRepositoryInterface::class,
            EloquentFoodOrderReportRepository::class,
        );
        $this->app->bind(
            FoodReportQueryServiceInterface::class,
            FoodReportQueryService::class,
        );
        $this->app->bind(
            FoodReportSpreadsheetExporterInterface::class,
            PhpSpreadsheetFoodReportExporter::class,
        );
        $this->app->bind(
            FoodReportMaxDeliveryInterface::class,
            FoodReportMaxDeliveryService::class,
        );
    }

    /**
     * Регистрирует artisan-команды модуля.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillFoodOrderItemsCommand::class,
            ]);
        }
    }
}
