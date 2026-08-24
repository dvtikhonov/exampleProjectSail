<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
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
use App\Contracts\Food\Menu\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Order\OrderSubmissionServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\Contracts\Max\MaxAdminBotTestSenderInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
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
use App\Repositories\Max\EloquentMaxUserRepository;
use App\Services\Auth\EloquentGatewayUserResolver;
use App\Services\Auth\LaravelGatewayAuthSession;
use App\Services\Auth\RequestGatewayUserContext;
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
use App\Services\Food\Menu\DishImageDeliveryService;
use App\Services\Food\Menu\DishImageUploadService;
use App\Services\Food\Menu\DishImageUrlResolver;
use App\Services\Food\Menu\MenuAvailabilityDateResolver;
use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use App\Services\Food\Menu\MenuCategoryAdminService;
use App\Services\Food\Menu\MenuQueryService;
use App\Services\Food\Order\CustomerOrderQueryService;
use App\Services\Food\Order\OrderSubmissionService;
use App\Services\Food\PhotoText\PhotoTextComboRefGrouper;
use App\Services\Food\PhotoText\PhotoTextDishLineResolver;
use App\Services\Food\PhotoText\PhotoTextManualOrderPlacementService;
use App\Services\Food\PhotoText\PhotoTextSchedulePlacementService;
use App\Services\Food\Review\OrderCustomerNotifyRecipientResolver;
use App\Services\Max\ConfigMaxMessengerRetryConfigFactory;
use App\Services\Max\ConfigMaxOrderNotificationConfigProvider;
use App\Services\Max\EnvMaxBotTokenProvider;
use App\Services\Max\Food\LaravelFoodOrderCustomerNotifier;
use App\Services\Max\Food\LaravelFoodOrderMaxNotifier;
use App\Services\Max\Food\LaravelOrderChatNotifier;
use App\Services\Max\LaravelMaxAdminBotTestSender;
use App\Services\Max\MaxAiAccessService;
use App\Services\Max\MaxLoadTestService;
use App\Services\Max\MaxUserDeliveryAddressService;
use App\Services\Max\MaxWebAppInitDataValidator;
use App\Services\Max\Menu\MaxManagerDailyMenuMessageBuilder;
use App\Services\Max\UiStand\MaxManagerDailyMenuNotifier;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use App\Services\Max\UiStand\MaxWebhookUpdateRouter;
use App\Support\Max\MaxAppRequestContext;
use App\Support\Max\MaxUiStandRecipientResolver;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
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
        $this->app->bind(DishImageUrlResolverInterface::class, DishImageUrlResolver::class);
        $this->app->bind(DishImageDeliveryInterface::class, DishImageDeliveryService::class);
        $this->app->bind(DishImageUploadInterface::class, DishImageUploadService::class);
        $this->app->bind(DishAdminRepositoryInterface::class, EloquentDishRepository::class);
        $this->app->bind(DishCatalogRepositoryInterface::class, EloquentDishRepository::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->bind(ManualOrderCartServiceInterface::class, ManualOrderCartService::class);
        $this->app->bind(ManualOrderUserQueryServiceInterface::class, ManualOrderUserQueryService::class);
        $this->app->bind(ManualOrderCustomerResolverInterface::class, ManualOrderCustomerResolver::class);
        $this->app->bind(ManualOrderQueryServiceInterface::class, ManualOrderQueryService::class);
        $this->app->bind(DraftAfterScanningOrderServiceInterface::class, DraftAfterScanningOrderService::class);
        $this->app->bind(PhotoTextComboRefGrouperInterface::class, PhotoTextComboRefGrouper::class);
        $this->app->bind(PhotoTextDishLineResolverInterface::class, PhotoTextDishLineResolver::class);
        $this->app->bind(PhotoTextManualOrderPlacementServiceInterface::class, PhotoTextManualOrderPlacementService::class);
        $this->app->bind(PhotoTextSchedulePlacementServiceInterface::class, PhotoTextSchedulePlacementService::class);
        $this->app->bind(CustomerOrderQueryServiceInterface::class, CustomerOrderQueryService::class);
        $this->app->bind(DishAdminServiceInterface::class, DishAdminService::class);
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
                    $app->make(Repository::class),
                );
            },
        );
        $this->app->bind(
            MenuQueryServiceInterface::class,
            function ($app): CachingMenuQueryService {
                return new CachingMenuQueryService(
                    $app->make(MenuQueryService::class),
                    $app->make(Repository::class),
                    (int) config('food.catalog_cache_ttl_seconds', 600),
                    (bool) config('food.catalog_cache_enabled', true),
                );
            },
        );
        $this->app->bind(OrderSubmissionServiceInterface::class, OrderSubmissionService::class);
        $this->app->bind(OrderCompositionSnapshotBuilderInterface::class, OrderCompositionSnapshotBuilder::class);
        $this->app->bind(OrderCompositionUpdateServiceInterface::class, OrderCompositionUpdateService::class);
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
        $this->app->bind(MaxUserDeliveryAddressInterface::class, MaxUserDeliveryAddressService::class);
        $this->app->bind(MaxUserRepositoryInterface::class, EloquentMaxUserRepository::class);
        $this->app->bind(MaxAiAccessServiceInterface::class, MaxAiAccessService::class);
        $this->app->bind(MaxMenuAvailabilityNotifierInterface::class, MaxMenuAvailabilityNotifier::class);
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
        $this->app->bind(
            MaxUiStandRecipientResolverInterface::class,
            MaxUiStandRecipientResolver::class,
        );
        $this->app->bind(
            MaxOrderNotificationConfigProviderInterface::class,
            ConfigMaxOrderNotificationConfigProvider::class,
        );
        $this->app->bind(FoodOrderMaxNotifierInterface::class, LaravelFoodOrderMaxNotifier::class);
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
