<?php

namespace App\Providers;

use App\Contracts\Food\Cart\CartDeliveryAddressServiceInterface;
use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Food\Chat\OrderChatAuthorizationServiceInterface;
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
use App\Contracts\Food\Menu\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryWriteRepositoryInterface;
use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderSubmissionServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminReviewReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAfterSubmitNotifierInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderManualAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Food\Order\OrderFromCartCreatorInterface;
use App\Contracts\Food\PhotoText\PhotoTextComboRefGrouperInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishLineResolverInterface;
use App\Contracts\Food\PhotoText\PhotoTextDishNameMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\Contracts\Food\Review\FoodOrderCustomerMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Food\Review\OrderReviewAuthorizationServiceInterface;
use App\Contracts\Food\Review\OrderReviewCompletionServiceInterface;
use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Infrastructure\Laravel\LaravelFoodOrderAfterSubmitNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderCustomerNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderMaxNotifier;
use App\Infrastructure\Laravel\LaravelOrderChatNotifier;
use App\Repositories\Food\Cart\EloquentCartRepository;
use App\Repositories\Food\Chat\EloquentOrderMessageRepository;
use App\Repositories\Food\Delivery\EloquentCustomerCategoryRepository;
use App\Repositories\Food\Delivery\EloquentDeliveryTierRepository;
use App\Repositories\Food\Menu\EloquentDailyMenuCatalogRepository;
use App\Repositories\Food\Menu\EloquentDishAdminRepository;
use App\Repositories\Food\Menu\EloquentDishAvailabilityRepository;
use App\Repositories\Food\Menu\EloquentDishCatalogRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryAvailabilityOffsetRepository;
use App\Repositories\Food\Menu\EloquentMenuCategoryRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminRepository;
use App\Repositories\Food\Order\EloquentFoodOrderCustomerReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderWriteRepository;
use App\Repositories\Food\Shared\EloquentRestaurantRepository;
use App\Services\Food\Cart\CartDeliveryAddressService;
use App\Services\Food\Cart\CartService;
use App\Services\Food\Chat\OrderChatAuthorizationService;
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
use App\Services\Food\Order\AdminOrderQueryService;
use App\Services\Food\Order\CustomerOrderQueryService;
use App\Services\Food\Order\CustomerOrderSubmissionService;
use App\Services\Food\Order\ManualOrderSubmissionService;
use App\Services\Food\Order\OrderFromCartCreator;
use App\Services\Food\PhotoText\PhotoTextComboRefGrouper;
use App\Services\Food\PhotoText\PhotoTextDishLineResolver;
use App\Services\Food\PhotoText\PhotoTextDishNameMatcher;
use App\Services\Food\PhotoText\PhotoTextManualOrderPlacementService;
use App\Services\Food\PhotoText\PhotoTextSchedulePlacementService;
use App\Services\Food\Review\OrderCustomerNotifyRecipientResolver;
use App\Services\Food\Review\OrderReviewAuthorizationService;
use App\Services\Food\Review\OrderReviewCompletionService;
use App\Services\Food\Review\OrderReviewStepHandler;
use App\Services\Food\Shared\FoodMoneyFormatter;
use App\Services\Max\Food\FoodOrderChatMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderCustomerMaxMessageBuilder;
use App\Services\Max\Menu\MaxManagerDailyMenuMessageBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * DI-привязки Food-слоя (меню, корзина, заказы, review, photo-text).
 */
class FoodServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует Food-контракты и сервисы в контейнере.
     */
    public function register(): void
    {
        $this->app->when(DishDefaultImageProvider::class)
            ->needs('$basePath')
            ->give(static fn (): string => base_path());
        $this->app->bind(DishImageUrlResolverInterface::class, DishImageUrlResolver::class);
        $this->app->bind(DishImageDeliveryInterface::class, DishImageDeliveryService::class);
        $this->app->bind(DishImageUploadInterface::class, DishImageUploadService::class);
        $this->app->bind(DishAdminRepositoryInterface::class, EloquentDishAdminRepository::class);
        $this->app->bind(DishAdminReadRepositoryInterface::class, EloquentDishAdminRepository::class);
        $this->app->bind(DishAdminWriteRepositoryInterface::class, EloquentDishAdminRepository::class);
        $this->app->bind(DishAdminBulkRepositoryInterface::class, EloquentDishAdminRepository::class);
        $this->app->bind(DishCatalogRepositoryInterface::class, EloquentDishCatalogRepository::class);
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
        $this->app->bind(DishAdminPhotoCoordinatorInterface::class, DishAdminPhotoCoordinator::class);
        $this->app->bind(DishBulkImportWriterInterface::class, DishBulkImportWriter::class);
        $this->app->bind(DishAdminServiceInterface::class, DishAdminService::class);
        $this->app->bind(DishSpreadsheetImportServiceInterface::class, DishSpreadsheetImportService::class);
        $this->app->bind(MenuCategoryAdminServiceInterface::class, MenuCategoryAdminService::class);
        $this->app->bind(MenuCatalogCacheInvalidatorInterface::class, MenuCatalogCacheInvalidator::class);
        $this->app->bind(DishAvailabilityRepositoryInterface::class, EloquentDishAvailabilityRepository::class);
        $this->app->bind(DishAvailabilityScheduleRepositoryInterface::class, EloquentDishAvailabilityRepository::class);
        $this->app->bind(DishAvailabilityFlagSyncRepositoryInterface::class, EloquentDishAvailabilityRepository::class);
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
        $this->app->bind(CustomerOrderSubmissionServiceInterface::class, CustomerOrderSubmissionService::class);
        $this->app->bind(ManualOrderSubmissionServiceInterface::class, ManualOrderSubmissionService::class);
        $this->app->bind(FoodOrderAfterSubmitNotifierInterface::class, LaravelFoodOrderAfterSubmitNotifier::class);
        $this->app->bind(OrderFromCartCreatorInterface::class, OrderFromCartCreator::class);
        $this->app->bind(OrderCompositionSnapshotBuilderInterface::class, OrderCompositionSnapshotBuilder::class);
        $this->app->bind(OrderCompositionUpdateServiceInterface::class, OrderCompositionUpdateService::class);
        $this->app->bind(OrderReviewAuthorizationServiceInterface::class, OrderReviewAuthorizationService::class);
        $this->app->bind(OrderReviewCompletionServiceInterface::class, OrderReviewCompletionService::class);
        $this->app->bind(OrderReviewStepHandlerInterface::class, OrderReviewStepHandler::class);
        $this->app->bind(OrderChatAuthorizationServiceInterface::class, OrderChatAuthorizationService::class);
        $this->app->bind(OrderChatServiceInterface::class, OrderChatService::class);
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
        $this->app->bind(
            RestaurantRepositoryInterface::class,
            EloquentRestaurantRepository::class,
        );
        $this->app->bind(FoodMoneyFormatterInterface::class, FoodMoneyFormatter::class);
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
            EloquentFoodOrderWriteRepository::class,
        );
        $this->app->bind(
            FoodOrderCustomerReadRepositoryInterface::class,
            EloquentFoodOrderCustomerReadRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminReadRepositoryInterface::class,
            EloquentFoodOrderAdminReadRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminReviewReadRepositoryInterface::class,
            EloquentFoodOrderAdminReadRepository::class,
        );
        $this->app->bind(
            FoodOrderManualAdminReadRepositoryInterface::class,
            EloquentFoodOrderAdminReadRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminRepositoryInterface::class,
            EloquentFoodOrderAdminRepository::class,
        );
        $this->app->bind(
            OrderMessageRepositoryInterface::class,
            EloquentOrderMessageRepository::class,
        );
        $this->app->bind(DailyMenuCatalogRepositoryInterface::class, EloquentDailyMenuCatalogRepository::class);
        $this->app->bind(DailyMenuLineCollectorInterface::class, DailyMenuLineCollector::class);
        $this->app->bind(
            MaxManagerDailyMenuMessageBuilderInterface::class,
            MaxManagerDailyMenuMessageBuilder::class,
        );
        $this->app->bind(
            FoodOrderCustomerMaxMessageBuilderInterface::class,
            FoodOrderCustomerMaxMessageBuilder::class,
        );
        $this->app->bind(
            FoodOrderChatMaxMessageBuilderInterface::class,
            FoodOrderChatMaxMessageBuilder::class,
        );
        $this->app->when(OrderCustomerNotifyRecipientResolver::class)
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
        $this->app->bind(
            OrderCustomerNotifyRecipientResolverInterface::class,
            OrderCustomerNotifyRecipientResolver::class,
        );
        $this->app->bind(FoodOrderMaxNotifierInterface::class, LaravelFoodOrderMaxNotifier::class);
        $this->app->bind(FoodOrderCustomerNotifierInterface::class, LaravelFoodOrderCustomerNotifier::class);
        $this->app->bind(OrderChatNotifierInterface::class, LaravelOrderChatNotifier::class);
    }
}
