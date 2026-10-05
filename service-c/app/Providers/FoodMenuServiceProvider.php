<?php

namespace App\Providers;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchClassifierInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchQueueInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionRepositoryInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSourceCollectorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenCaptureGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenStoreInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\Contracts\Food\ComboCatalog\ComboCatalogPromptBuilderInterface;
use App\Contracts\Food\ComboCatalog\WeightLabelCanonicalizerInterface;
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
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\LlmCallLoggerInterface;
use App\Infrastructure\Briskly\HttpBrisklyCatalogGateway;
use App\Infrastructure\Briskly\HttpBrisklySyncMatchOrchestrator;
use App\Infrastructure\Briskly\HttpBrisklySyncTokenCaptureGateway;
use App\Infrastructure\Briskly\HttpBrisklySyncVpsCatalogGateway;
use App\Infrastructure\Laravel\LaravelBrisklySyncMatchQueue;
use App\Infrastructure\Laravel\LaravelMaxLogLlmCallLogger;
use App\Infrastructure\Laravel\PhpSpreadsheetDishRowsReader;
use App\Repositories\Food\BrisklySync\EloquentBrisklySyncSessionRepository;
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
use App\Services\Food\BrisklySync\BrisklySyncMatchClassifier;
use App\Services\Food\BrisklySync\BrisklySyncSessionService;
use App\Services\Food\BrisklySync\BrisklySyncSourceCollector;
use App\Services\Food\BrisklySync\CacheBrisklySyncTokenStore;
use App\Services\Food\BrisklySync\LocalBrisklySyncVpsCatalog;
use App\Services\Food\ComboCatalog\ComboCatalogPromptBuilder;
use App\Services\Food\ComboCatalog\WeightLabelCanonicalizer;
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
use Illuminate\Support\Facades\Log;
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
        $this->app->bind(BrisklySyncSourceCollectorInterface::class, BrisklySyncSourceCollector::class);
        $this->app->bind(
            BrisklySyncVpsCatalogPortInterface::class,
            function ($app): BrisklySyncVpsCatalogPortInterface {
                if ((bool) config('briskly_sync.source_remote')) {
                    return new HttpBrisklySyncVpsCatalogGateway(
                        $app->make(HttpClientInterface::class),
                        (string) config('briskly_sync.source_base_url', ''),
                        (string) config('phototext.agent_token', ''),
                        (int) config('briskly_sync.source_timeout_seconds', 30),
                    );
                }

                return new LocalBrisklySyncVpsCatalog(
                    $app->make(RestaurantRepositoryInterface::class),
                    $app->make(MenuCategoryReadRepositoryInterface::class),
                    $app->make(BrisklySyncSourceCollectorInterface::class),
                );
            },
        );
        $this->app->bind(WeightLabelCanonicalizerInterface::class, WeightLabelCanonicalizer::class);
        $this->app->bind(ComboCatalogPromptBuilderInterface::class, ComboCatalogPromptBuilder::class);
        $this->app->bind(BrisklySyncSessionRepositoryInterface::class, EloquentBrisklySyncSessionRepository::class);
        $this->app->bind(BrisklySyncTokenStoreInterface::class, CacheBrisklySyncTokenStore::class);
        $this->app->bind(BrisklySyncMatchClassifierInterface::class, BrisklySyncMatchClassifier::class);
        $this->app->bind(
            BrisklyCatalogGatewayInterface::class,
            function ($app): HttpBrisklyCatalogGateway {
                return new HttpBrisklyCatalogGateway(
                    $app->make(HttpClientInterface::class),
                    (string) config('briskly_sync.briskly_base_url'),
                    (int) config('briskly_sync.briskly_timeout_seconds', 30),
                    (int) config('briskly_sync.snapshot_page_limit', 100),
                    (int) config('briskly_sync.snapshot_max_pages', 50),
                    (int) config('briskly_sync.briskly_delay_ms', 200),
                );
            },
        );
        $this->app->bind(
            LlmCallLoggerInterface::class,
            static fn (): LaravelMaxLogLlmCallLogger => new LaravelMaxLogLlmCallLogger(
                Log::channel('max_log'),
            ),
        );
        $this->app->bind(
            BrisklySyncMatchOrchestratorInterface::class,
            function ($app): HttpBrisklySyncMatchOrchestrator {
                return new HttpBrisklySyncMatchOrchestrator(
                    $app->make(HttpClientInterface::class),
                    (string) config('briskly_sync.orchestrator_base_url'),
                    (int) config('briskly_sync.orchestrator_timeout_seconds', 120),
                    $app->make(LlmCallLoggerInterface::class),
                );
            },
        );
        $this->app->bind(
            BrisklySyncTokenCaptureGatewayInterface::class,
            function ($app): HttpBrisklySyncTokenCaptureGateway {
                return new HttpBrisklySyncTokenCaptureGateway(
                    $app->make(HttpClientInterface::class),
                    (string) config('briskly_sync.orchestrator_base_url'),
                    (string) config('briskly_sync.capture_secret', ''),
                    (int) config('briskly_sync.capture_timeout_seconds', 30),
                );
            },
        );
        $this->app->bind(
            BrisklySyncMatchQueueInterface::class,
            function ($app): LaravelBrisklySyncMatchQueue {
                return new LaravelBrisklySyncMatchQueue(
                    $app->make(JobDispatcherInterface::class),
                    (int) config('briskly_sync.match_job_timeout_seconds', 180),
                );
            },
        );
        $this->app->bind(
            BrisklySyncSessionServiceInterface::class,
            function ($app): BrisklySyncSessionService {
                return new BrisklySyncSessionService(
                    $app->make(BrisklySyncSessionRepositoryInterface::class),
                    $app->make(BrisklySyncTokenStoreInterface::class),
                    $app->make(BrisklySyncTokenCaptureGatewayInterface::class),
                    $app->make(BrisklySyncVpsCatalogPortInterface::class),
                    $app->make(BrisklyCatalogGatewayInterface::class),
                    $app->make(BrisklySyncMatchOrchestratorInterface::class),
                    $app->make(BrisklySyncMatchQueueInterface::class),
                    $app->make(BrisklySyncMatchClassifierInterface::class),
                    $app->make(ComboCatalogPromptBuilderInterface::class),
                    $app->make(CacheStoreInterface::class),
                    (int) config('briskly_sync.token_ttl_seconds', 7200),
                    (int) config('briskly_sync.section_cap', 25),
                    (float) config('briskly_sync.large_delta_ratio', 0.5),
                    (int) config('briskly_sync.apply_lock_ttl_seconds', 120),
                );
            },
        );
        $this->app->bind(
            MaxManagerDailyMenuMessageBuilderInterface::class,
            MaxManagerDailyMenuMessageBuilder::class,
        );
    }
}
