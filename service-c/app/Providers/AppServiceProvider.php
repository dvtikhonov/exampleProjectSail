<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Food\Cart\CartDeliveryAddressServiceInterface;
use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Food\Chat\OrderChatNotifierInterface;
use App\Contracts\Food\Chat\OrderChatServiceInterface;
use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Composition\OrderCompositionSnapshotBuilderInterface;
use App\Contracts\Food\Composition\OrderCompositionUpdateServiceInterface;
use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Delivery\DeliveryTierRepositoryInterface;
use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\Menu\DailyMenuCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DailyMenuLineCollectorInterface;
use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleServiceInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DishImageDeliveryInterface;
use App\Contracts\Food\Menu\DishImageUploadInterface;
use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\DishSpreadsheetImportServiceInterface;
use App\Contracts\Food\Menu\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Order\CustomerOrderSubmissionServiceInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxAdminBotTestSenderInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxMiniAppAuthServiceInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\LocalFileWriterInterface;
use App\Contracts\Shared\RequestTimingRecorderInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\Http\Resolvers\AuthenticatedMaxUserResolver;
use App\Infrastructure\Laravel\LaravelApplicationConfig;
use App\Infrastructure\Laravel\LaravelApplicationEnvironment;
use App\Infrastructure\Laravel\LaravelCacheStore;
use App\Infrastructure\Laravel\LaravelClock;
use App\Infrastructure\Laravel\LaravelFileStorage;
use App\Infrastructure\Laravel\LaravelFoodOrderCustomerNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderMaxNotifier;
use App\Infrastructure\Laravel\LaravelGatewayAuthSession;
use App\Infrastructure\Laravel\LaravelHttpClient;
use App\Infrastructure\Laravel\LaravelJobDispatcher;
use App\Infrastructure\Laravel\LaravelLocalFileWriter;
use App\Infrastructure\Laravel\LaravelMaxAdminBotTestSender;
use App\Infrastructure\Laravel\LaravelMaxMiniAppTokenIssuer;
use App\Infrastructure\Laravel\LaravelOrderChatNotifier;
use App\Infrastructure\Laravel\LaravelRequestTimingRecorder;
use App\Infrastructure\Laravel\LaravelTransactionManager;
use App\Infrastructure\Laravel\RequestGatewayUserContext;
use App\Repositories\Auth\EloquentGatewayUserResolver;
use App\Repositories\Food\Cart\EloquentCartRepository;
use App\Repositories\Food\Chat\EloquentOrderMessageRepository;
use App\Repositories\Food\Delivery\EloquentCustomerCategoryRepository;
use App\Repositories\Food\Delivery\EloquentDeliveryTierRepository;
use App\Repositories\Food\Menu\EloquentDailyMenuCatalogRepository;
use App\Repositories\Food\Menu\EloquentDishAvailabilityRepository;
use App\Repositories\Food\Menu\EloquentDishRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryAvailabilityOffsetRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminRepository;
use App\Repositories\Food\Order\EloquentFoodOrderRepository;
use App\Repositories\Food\Shared\EloquentRestaurantRepository;
use App\Repositories\Max\EloquentMaxLoadTestDataRepository;
use App\Repositories\Max\EloquentMaxUserRepository;
use App\Services\Food\Cart\CartDeliveryAddressService;
use App\Services\Food\Cart\CartService;
use App\Services\Food\Chat\OrderChatService;
use App\Services\Food\Composition\OrderCompositionSnapshotBuilder;
use App\Services\Food\Composition\OrderCompositionUpdateService;
use App\Services\Food\ManualOrder\DraftAfterScanningOrderService;
use App\Services\Food\ManualOrder\ManualOrderCartService;
use App\Services\Food\ManualOrder\ManualOrderCustomerResolver;
use App\Services\Food\ManualOrder\ManualOrderQueryService;
use App\Services\Food\ManualOrder\ManualOrderUserQueryService;
use App\Services\Food\Menu\CachingMenuAvailabilityDateResolver;
use App\Services\Food\Menu\CachingMenuQueryService;
use App\Services\Food\Menu\DailyMenuLineCollector;
use App\Services\Food\Menu\DishAdminService;
use App\Services\Food\Menu\DishAvailabilityScheduleService;
use App\Services\Food\Menu\DishDefaultImageProvider;
use App\Services\Food\Menu\DishImageDeliveryService;
use App\Services\Food\Menu\DishImageUploadService;
use App\Services\Food\Menu\DishImageUrlResolver;
use App\Services\Food\Menu\DishSpreadsheetImportService;
use App\Services\Food\Menu\MenuAvailabilityDateResolver;
use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use App\Services\Food\Menu\MenuCategoryAdminService;
use App\Services\Food\Menu\MenuQueryService;
use App\Services\Food\Order\AdminOrderQueryService;
use App\Services\Food\Order\CustomerOrderQueryService;
use App\Services\Food\Order\CustomerOrderSubmissionService;
use App\Services\Food\Order\ManualOrderSubmissionService;
use App\Services\Food\PhotoText\PhotoTextComboRefGrouper;
use App\Services\Food\PhotoText\PhotoTextDishLineResolver;
use App\Services\Food\PhotoText\PhotoTextDishNameMatcher;
use App\Services\Food\PhotoText\PhotoTextManualOrderPlacementService;
use App\Services\Food\PhotoText\PhotoTextSchedulePlacementService;
use App\Services\Food\Review\OrderCustomerNotifyRecipientResolver;
use App\Services\Food\Review\OrderReviewStepHandler;
use App\Services\Max\ConfigMaxMessengerRetryConfigFactory;
use App\Services\Max\ConfigMaxOrderNotificationConfigProvider;
use App\Services\Max\EnvMaxBotTokenProvider;
use App\Services\Max\CachingMaxAiAccessService;
use App\Services\Max\MaxAiAccessService;
use App\Services\Max\MaxLoadTestService;
use App\Services\Max\MaxMessengerNotificationSender;
use App\Services\Max\MaxMiniAppAuthService;
use App\Services\Max\MaxUserDeliveryAddressService;
use App\Services\Max\MaxWebAppInitDataValidator;
use App\Services\Max\Menu\MaxManagerDailyMenuMessageBuilder;
use App\Services\Max\UiStand\MaxCallbackHandler;
use App\Services\Max\UiStand\MaxManagerDailyMenuNotifier;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use App\Services\Max\UiStand\MaxWebhookSubscriber;
use App\Services\Max\UiStand\MaxWebhookUpdateRouter;
use App\Support\Max\MaxAppRequestContext;
use App\Support\Max\MaxUiStandRecipientResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;

/**
 * Регистрация DI-привязок и настройка публичных URL приложения.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(GatewayUserContextInterface::class, RequestGatewayUserContext::class);
        $this->app->bind(GatewayUserResolverInterface::class, EloquentGatewayUserResolver::class);
        $this->app->bind(GatewayAuthSessionInterface::class, LaravelGatewayAuthSession::class);
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);
        $this->app->bind(ClockInterface::class, LaravelClock::class);
        $this->app->bind(JobDispatcherInterface::class, LaravelJobDispatcher::class);
        $this->app->bind(FileStorageInterface::class, LaravelFileStorage::class);
        $this->app->bind(ApplicationConfigInterface::class, LaravelApplicationConfig::class);
        $this->app->bind(ApplicationEnvironmentInterface::class, LaravelApplicationEnvironment::class);
        $this->app->bind(HttpClientInterface::class, LaravelHttpClient::class);
        $this->app->bind(CacheStoreInterface::class, LaravelCacheStore::class);
        $this->app->bind(LocalFileWriterInterface::class, LaravelLocalFileWriter::class);
        $this->app->bind(RequestTimingRecorderInterface::class, LaravelRequestTimingRecorder::class);
        $this->app->bind(LoggerInterface::class, static fn (): LoggerInterface => Log::channel());
        $this->app->bind(MaxMiniAppTokenIssuerInterface::class, LaravelMaxMiniAppTokenIssuer::class);
        $this->app->bind(MaxLoadTestDataRepositoryInterface::class, EloquentMaxLoadTestDataRepository::class);
        $this->app->when(DishDefaultImageProvider::class)
            ->needs('$basePath')
            ->give(static fn (): string => base_path());
        $this->app->bind(DishImageUrlResolverInterface::class, DishImageUrlResolver::class);
        $this->app->bind(DishImageDeliveryInterface::class, DishImageDeliveryService::class);
        $this->app->bind(DishImageUploadInterface::class, DishImageUploadService::class);
        $this->app->bind(DishAdminRepositoryInterface::class, EloquentDishRepository::class);
        $this->app->bind(DishCatalogRepositoryInterface::class, EloquentDishRepository::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartDraftRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartItemRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartLifecycleRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->bind(CartDeliveryAddressServiceInterface::class, CartDeliveryAddressService::class);
        $this->app->bind(ManualOrderCartServiceInterface::class, ManualOrderCartService::class);
        $this->app->bind(ManualOrderUserQueryServiceInterface::class, ManualOrderUserQueryService::class);
        $this->app->bind(ManualOrderCustomerResolverInterface::class, ManualOrderCustomerResolver::class);
        $this->app->bind(ManualOrderQueryServiceInterface::class, ManualOrderQueryService::class);
        $this->app->bind(DraftAfterScanningOrderServiceInterface::class, DraftAfterScanningOrderService::class);
        $this->app->bind(PhotoTextComboRefGrouperInterface::class, PhotoTextComboRefGrouper::class);
        $this->app->bind(PhotoTextDishNameMatcherInterface::class, PhotoTextDishNameMatcher::class);
        $this->app->bind(PhotoTextDishLineResolverInterface::class, PhotoTextDishLineResolver::class);
        $this->app->bind(PhotoTextManualOrderPlacementServiceInterface::class, PhotoTextManualOrderPlacementService::class);
        $this->app->bind(PhotoTextSchedulePlacementServiceInterface::class, PhotoTextSchedulePlacementService::class);
        $this->app->bind(CustomerOrderQueryServiceInterface::class, CustomerOrderQueryService::class);
        $this->app->bind(AdminOrderQueryServiceInterface::class, AdminOrderQueryService::class);
        $this->app->bind(DishAdminServiceInterface::class, DishAdminService::class);
        $this->app->bind(DishSpreadsheetImportServiceInterface::class, DishSpreadsheetImportService::class);
        $this->app->bind(MenuCategoryAdminServiceInterface::class, MenuCategoryAdminService::class);
        $this->app->bind(MenuCatalogCacheInvalidatorInterface::class, MenuCatalogCacheInvalidator::class);
        $this->app->bind(DishAvailabilityRepositoryInterface::class, EloquentDishAvailabilityRepository::class);
        $this->app->bind(DishAvailabilityScheduleServiceInterface::class, DishAvailabilityScheduleService::class);
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
                );
            },
        );
        $this->app->bind(
            MenuQueryServiceInterface::class,
            function ($app): CachingMenuQueryService {
                return new CachingMenuQueryService(
                    $app->make(MenuQueryService::class),
                    $app->make(CacheStoreInterface::class),
                    (int) config('food.catalog_cache_ttl_seconds', 600),
                    (bool) config('food.catalog_cache_enabled', true),
                );
            },
        );
        $this->app->bind(CustomerOrderSubmissionServiceInterface::class, CustomerOrderSubmissionService::class);
        $this->app->bind(ManualOrderSubmissionServiceInterface::class, ManualOrderSubmissionService::class);
        $this->app->bind(OrderCompositionSnapshotBuilderInterface::class, OrderCompositionSnapshotBuilder::class);
        $this->app->bind(OrderCompositionUpdateServiceInterface::class, OrderCompositionUpdateService::class);
        $this->app->bind(OrderReviewStepHandlerInterface::class, OrderReviewStepHandler::class);
        $this->app->bind(OrderChatServiceInterface::class, OrderChatService::class);
        $this->app->bind(
            MenuCategoryRepositoryInterface::class,
            EloquentMenuCategoryRepository::class,
        );
        $this->app->bind(
            RestaurantRepositoryInterface::class,
            EloquentRestaurantRepository::class,
        );
        $this->app->bind(
            MenuReadRepositoryInterface::class,
            EloquentRestaurantRepository::class,
        );
        $this->app->bind(
            DeliveryTierRepositoryInterface::class,
            EloquentDeliveryTierRepository::class,
        );
        $this->app->bind(
            CustomerCategoryRepositoryInterface::class,
            EloquentCustomerCategoryRepository::class,
        );
        $this->app->bind(
            FoodOrderWriteRepositoryInterface::class,
            EloquentFoodOrderRepository::class,
        );
        $this->app->bind(
            FoodOrderCustomerReadRepositoryInterface::class,
            EloquentFoodOrderRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminReadRepositoryInterface::class,
            EloquentFoodOrderRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminRepositoryInterface::class,
            EloquentFoodOrderAdminRepository::class,
        );
        $this->app->bind(
            OrderMessageRepositoryInterface::class,
            EloquentOrderMessageRepository::class,
        );

        $this->app->bind(MaxAdminBotTestSenderInterface::class, LaravelMaxAdminBotTestSender::class);
        $this->app->bind(MaxLoadTestServiceInterface::class, MaxLoadTestService::class);
        $this->app->bind(AuthenticatedMaxUserResolverInterface::class, AuthenticatedMaxUserResolver::class);
        $this->app->bind(MaxUserDeliveryAddressInterface::class, MaxUserDeliveryAddressService::class);
        $this->app->bind(MaxUserRepositoryInterface::class, EloquentMaxUserRepository::class);
        $this->app->bind(
            MaxAiAccessServiceInterface::class,
            function ($app): CachingMaxAiAccessService {
                return new CachingMaxAiAccessService(
                    $app->make(MaxAiAccessService::class),
                    $app->make(CacheStoreInterface::class),
                    (bool) config('max.ai_access_cache_enabled', true),
                );
            },
        );
        $this->app->bind(MaxMenuAvailabilityNotifierInterface::class, MaxMenuAvailabilityNotifier::class);
        $this->app->bind(
            MaxMessengerNotificationSenderInterface::class,
            MaxMessengerNotificationSender::class,
        );
        $this->app->bind(DailyMenuCatalogRepositoryInterface::class, EloquentDailyMenuCatalogRepository::class);
        $this->app->bind(DailyMenuLineCollectorInterface::class, DailyMenuLineCollector::class);
        $this->app->bind(
            MaxManagerDailyMenuMessageBuilderInterface::class,
            MaxManagerDailyMenuMessageBuilder::class,
        );
        $this->app->bind(MaxManagerDailyMenuNotifierInterface::class, MaxManagerDailyMenuNotifier::class);
        $this->app->bind(MaxBotTokenProviderInterface::class, EnvMaxBotTokenProvider::class);
        $this->app->bind(MaxMessengerClientInterface::class, function ($app): MaxMessengerClientInterface {
            // Laravel: MAX_MESSENGER_DRIVER=null в .env → PHP null; учитываем null / '' / 'null'.
            $driver = config('max.messenger_driver', 'http');
            if ($driver === null || $driver === '' || $driver === 'null') {
                return new NullMaxMessengerClient;
            }

            return new HttpMaxMessengerClient(
                tokenProvider: $app->make(MaxBotTokenProviderInterface::class),
                retryConfig: $app->make(ConfigMaxMessengerRetryConfigFactory::class)->make(),
            );
        });
        $this->app->bind(MaxWebhookUpdateRouterInterface::class, MaxWebhookUpdateRouter::class);
        $this->app->bind(MaxWebAppInitDataValidatorInterface::class, MaxWebAppInitDataValidator::class);
        $this->app->bind(MaxMiniAppAuthServiceInterface::class, MaxMiniAppAuthService::class);
        $this->app->bind(
            MaxUiStandRecipientResolverInterface::class,
            MaxUiStandRecipientResolver::class,
        );
        $this->app->bind(
            MaxOrderNotificationConfigProviderInterface::class,
            ConfigMaxOrderNotificationConfigProvider::class,
        );
        $this->app->bind(FoodOrderMaxNotifierInterface::class, LaravelFoodOrderMaxNotifier::class);
        $this->app->when(OrderCustomerNotifyRecipientResolver::class)
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
        $this->app->when([
            MaxCallbackHandler::class,
            MaxMenuAvailabilityNotifier::class,
            MaxManagerDailyMenuNotifier::class,
            MaxWebhookUpdateRouter::class,
            MaxMessengerNotificationSender::class,
            MaxWebhookSubscriber::class,
        ])
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
        $this->app->bind(
            OrderCustomerNotifyRecipientResolverInterface::class,
            OrderCustomerNotifyRecipientResolver::class,
        );
        $this->app->bind(FoodOrderCustomerNotifierInterface::class, LaravelFoodOrderCustomerNotifier::class);
        $this->app->bind(OrderChatNotifierInterface::class, LaravelOrderChatNotifier::class);
    }

    /**
     * Настраивает схему и корневой URL для HTTPS-туннеля и прокси.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole() && MaxAppRequestContext::isPublicTunnelRequest()) {
            $publicUrl = MaxAppRequestContext::publicAppUrl();

            if ($publicUrl !== null) {
                URL::forceScheme('https');
                URL::forceRootUrl($publicUrl);

                return;
            }
        }

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');

            // APP_URL указывает на публичный HTTPS-туннель — генерируем asset/API URL
            // от него, а не от https://127.0.0.1:8083 при локальном curl/прокси.
            if (! $this->app->runningInConsole()) {
                URL::forceRootUrl(rtrim($appUrl, '/'));
            }

            return;
        }

        if (! $this->app->runningInConsole()
            && request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }
    }
}
