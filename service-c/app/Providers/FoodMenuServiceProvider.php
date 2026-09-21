<?php

namespace App\Providers;

use App\Contracts\Food\Menu\DailyMenuCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DailyMenuLineCollectorInterface;
use App\Contracts\Food\Menu\DishAdminBulkRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminPhotoCoordinatorInterface;
use App\Contracts\Food\Menu\DishAdminReadRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\DishAdminWriteRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityFlagSyncRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityGridServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleWriterInterface;
use App\Contracts\Food\Menu\DishAvailabilitySyncServiceInterface;
use App\Contracts\Food\Menu\DishBulkImportWriterInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DishImageDeliveryInterface;
use App\Contracts\Food\Menu\DishImageUploadInterface;
use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\DishSpreadsheetImportServiceInterface;
use App\Contracts\Food\Menu\DishSpreadsheetRowsReaderInterface;
use App\Contracts\Food\Menu\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryWriteRepositoryInterface;
use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Infrastructure\Laravel\PhpSpreadsheetDishRowsReader;
use App\Repositories\Food\Menu\EloquentDailyMenuCatalogRepository;
use App\Repositories\Food\Menu\EloquentDishAdminBulkRepository;
use App\Repositories\Food\Menu\EloquentDishAdminReadRepository;
use App\Repositories\Food\Menu\EloquentDishAdminRepository;
use App\Repositories\Food\Menu\EloquentDishAdminWriteRepository;
use App\Repositories\Food\Menu\EloquentDishAvailabilityFlagSyncRepository;
use App\Repositories\Food\Menu\EloquentDishAvailabilityRepository;
use App\Repositories\Food\Menu\EloquentDishAvailabilityScheduleRepository;
use App\Repositories\Food\Menu\EloquentDishCatalogRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryAvailabilityOffsetRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryRepository;
use App\Services\Food\Menu\CachingMenuAvailabilityDateResolver;
use App\Services\Food\Menu\CachingMenuQueryService;
use App\Services\Food\Menu\DailyMenuLineCollector;
use App\Services\Food\Menu\DishAdminPhotoCoordinator;
use App\Services\Food\Menu\DishAdminService;
use App\Services\Food\Menu\DishAvailabilityGridService;
use App\Services\Food\Menu\DishAvailabilityScheduleService;
use App\Services\Food\Menu\DishAvailabilityScheduleWriter;
use App\Services\Food\Menu\DishAvailabilitySyncService;
use App\Services\Food\Menu\DishBulkImportWriter;
use App\Services\Food\Menu\DishDefaultImageProvider;
use App\Services\Food\Menu\DishImageDeliveryService;
use App\Services\Food\Menu\DishImageUploadService;
use App\Services\Food\Menu\DishImageUrlResolver;
use App\Services\Food\Menu\DishSpreadsheetImportService;
use App\Services\Food\Menu\MenuAvailabilityDateResolver;
use App\Services\Food\Menu\MenuCachePayloadHydrator;
use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use App\Services\Food\Menu\MenuCategoryAdminService;
use App\Services\Food\Menu\MenuQueryService;
use App\Services\Max\Menu\MaxManagerDailyMenuMessageBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * DI-привязки Food Menu (каталог, блюда, availability).
 */
class FoodMenuServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы меню.
     */
    public function register(): void
    {
        $this->app->when(DishDefaultImageProvider::class)
            ->needs('$basePath')
            ->give(static fn (): string => base_path());
        $this->app->bind(DishImageUrlResolverInterface::class, DishImageUrlResolver::class);
        $this->app->bind(DishImageDeliveryInterface::class, DishImageDeliveryService::class);
        $this->app->bind(DishImageUploadInterface::class, DishImageUploadService::class);
        $this->app->bind(DishAdminReadRepositoryInterface::class, EloquentDishAdminReadRepository::class);
        $this->app->bind(DishAdminWriteRepositoryInterface::class, EloquentDishAdminWriteRepository::class);
        $this->app->bind(DishAdminBulkRepositoryInterface::class, EloquentDishAdminBulkRepository::class);
        $this->app->bind(DishAdminRepositoryInterface::class, EloquentDishAdminRepository::class);
        $this->app->bind(DishCatalogRepositoryInterface::class, EloquentDishCatalogRepository::class);
        $this->app->bind(DishAdminPhotoCoordinatorInterface::class, DishAdminPhotoCoordinator::class);
        $this->app->bind(DishBulkImportWriterInterface::class, DishBulkImportWriter::class);
        $this->app->bind(DishAdminServiceInterface::class, DishAdminService::class);
        $this->app->bind(DishSpreadsheetRowsReaderInterface::class, PhpSpreadsheetDishRowsReader::class);
        $this->app->bind(DishSpreadsheetImportServiceInterface::class, DishSpreadsheetImportService::class);
        $this->app->bind(MenuCategoryAdminServiceInterface::class, MenuCategoryAdminService::class);
        $this->app->bind(MenuCatalogCacheInvalidatorInterface::class, MenuCatalogCacheInvalidator::class);
        $this->app->bind(DishAvailabilityScheduleRepositoryInterface::class, EloquentDishAvailabilityScheduleRepository::class);
        $this->app->bind(DishAvailabilityFlagSyncRepositoryInterface::class, EloquentDishAvailabilityFlagSyncRepository::class);
        $this->app->bind(DishAvailabilityRepositoryInterface::class, EloquentDishAvailabilityRepository::class);
        $this->app->bind(DishAvailabilityGridServiceInterface::class, DishAvailabilityGridService::class);
        $this->app->bind(DishAvailabilityScheduleWriterInterface::class, DishAvailabilityScheduleWriter::class);
        $this->app->bind(DishAvailabilityScheduleServiceInterface::class, DishAvailabilityScheduleService::class);
        $this->app->bind(DishAvailabilitySyncServiceInterface::class, DishAvailabilitySyncService::class);
        $this->app->bind(
            MenuCategoryAvailabilityOffsetRepositoryInterface::class,
            EloquentMenuCategoryAvailabilityOffsetRepository::class,
        );
        $this->app->bind(
            MenuAvailabilityDateResolverInterface::class,
            function ($app): CachingMenuAvailabilityDateResolver {
                return new CachingMenuAvailabilityDateResolver(
                    $app->make(MenuAvailabilityDateResolver::class),
                    $app->make(CacheStoreInterface::class),
                    $app->make(ClockInterface::class),
                );
            },
        );
        $this->app->bind(
            MenuQueryServiceInterface::class,
            function ($app): CachingMenuQueryService {
                return new CachingMenuQueryService(
                    $app->make(MenuQueryService::class),
                    $app->make(CacheStoreInterface::class),
                    $app->make(MenuCachePayloadHydrator::class),
                    (int) config('food.catalog_cache_ttl_seconds', 600),
                    (bool) config('food.catalog_cache_enabled', true),
                );
            },
        );
        $this->app->bind(
            MenuCategoryRepositoryInterface::class,
            EloquentMenuCategoryRepository::class,
        );
        $this->app->bind(
            MenuCategoryReadRepositoryInterface::class,
            EloquentMenuCategoryRepository::class,
        );
        $this->app->bind(
            MenuCategoryWriteRepositoryInterface::class,
            EloquentMenuCategoryRepository::class,
        );
        $this->app->bind(DailyMenuCatalogRepositoryInterface::class, EloquentDailyMenuCatalogRepository::class);
        $this->app->bind(DailyMenuLineCollectorInterface::class, DailyMenuLineCollector::class);
        $this->app->bind(
            MaxManagerDailyMenuMessageBuilderInterface::class,
            MaxManagerDailyMenuMessageBuilder::class,
        );
    }
}
