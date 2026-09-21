<?php

namespace App\Providers;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\Contracts\Food\Chat\OrderChatAuthorizationServiceInterface;
use App\Contracts\Food\Chat\OrderChatNotifierInterface;
use App\Contracts\Food\Chat\OrderChatServiceInterface;
use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Composition\ComboPairValidatorInterface;
use App\Contracts\Food\Composition\OrderCompositionSnapshotBuilderInterface;
use App\Contracts\Food\Composition\OrderCompositionUpdateServiceInterface;
use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Delivery\DeliveryTierRepositoryInterface;
use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCustomerResolverInterface;
use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\Order\AdminOrderDetailQueryServiceInterface;
use App\Contracts\Food\Order\AdminOrderListQueryServiceInterface;
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
use App\Contracts\Food\Order\OrderItemsSnapshotBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderCustomerCompositionMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCustomerMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderCustomerStatusMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\FoodOrderReviewNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\Contracts\Food\Review\FoodOrderUiStandNewRequestMaxMessageBuilderInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\Contracts\Food\Review\OrderReviewAuthorizationServiceInterface;
use App\Contracts\Food\Review\OrderReviewCompletionServiceInterface;
use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Food\Review\OrderStatusResolverInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\Contracts\Shared\ClockInterface;
use App\Enums\Food\Review\OrderReviewStep;
use App\Infrastructure\Laravel\LaravelFoodOrderAfterSubmitNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderCompositionNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderCustomerNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderManualCreatorNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderMaxNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderReviewNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderStatusNotifier;
use App\Infrastructure\Laravel\LaravelOrderChatNotifier;
use App\Repositories\Food\Chat\EloquentOrderMessageRepository;
use App\Repositories\Food\Delivery\EloquentCustomerCategoryRepository;
use App\Repositories\Food\Delivery\EloquentDeliveryTierRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminRepository;
use App\Repositories\Food\Order\EloquentFoodOrderAdminReviewReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderCustomerReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderManualAdminReadRepository;
use App\Repositories\Food\Order\EloquentFoodOrderWriteRepository;
use App\Repositories\Food\Shared\EloquentMenuReadRepository;
use App\Repositories\Food\Shared\EloquentRestaurantRepository;
use App\Services\Food\Chat\OrderChatAuthorizationService;
use App\Services\Food\Chat\OrderChatService;
use App\Services\Food\Composition\ComboPairValidator;
use App\Services\Food\Composition\OrderCompositionSnapshotBuilder;
use App\Services\Food\Composition\OrderCompositionUpdateService;
use App\Services\Food\ManualOrder\DraftAfterScanningOrderService;
use App\Services\Food\ManualOrder\ManualOrderCartService;
use App\Services\Food\ManualOrder\ManualOrderCustomerResolver;
use App\Services\Food\ManualOrder\ManualOrderQueryService;
use App\Services\Food\ManualOrder\ManualOrderUserQueryService;
use App\Services\Food\Order\AdminOrderDetailQueryService;
use App\Services\Food\Order\AdminOrderListQueryService;
use App\Services\Food\Order\AdminOrderQueryService;
use App\Services\Food\Order\CustomerOrderQueryService;
use App\Services\Food\Order\CustomerOrderSubmissionService;
use App\Services\Food\Order\ManualOrderSubmissionService;
use App\Services\Food\Order\OrderFromCartCreator;
use App\Services\Food\Order\OrderItemsSnapshotBuilder;
use App\Services\Food\Review\OrderCustomerNotifyRecipientResolver;
use App\Services\Food\Review\OrderReviewAuthorizationService;
use App\Services\Food\Review\OrderReviewCompletionService;
use App\Services\Food\Review\OrderReviewStepHandler;
use App\Services\Food\Review\OrderReviewUpdateFactory;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Review\UpdateStep\AddressOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\CompositionOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\PaymentOrderReviewUpdateStepHandler;
use App\Services\Food\Shared\FoodMoneyFormatter;
use App\Services\Max\Food\FoodOrderChatMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderCustomerCompositionChangedMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderCustomerMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderCustomerStatusMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderManualOrderCreatorConfirmedMaxMessageBuilder;
use App\Services\Max\Food\FoodOrderUiStandNewRequestMaxMessageBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * DI-привязки Food Order (заказы, review, chat, notifiers, shared food).
 */
class FoodOrderServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы заказов / review / уведомлений.
     */
    public function register(): void
    {
        $this->app->bind(ManualOrderCartServiceInterface::class, ManualOrderCartService::class);
        $this->app->bind(ManualOrderUserQueryServiceInterface::class, ManualOrderUserQueryService::class);
        $this->app->bind(ManualOrderCustomerResolverInterface::class, ManualOrderCustomerResolver::class);
        $this->app->bind(ManualOrderQueryServiceInterface::class, ManualOrderQueryService::class);
        $this->app->bind(DraftAfterScanningOrderServiceInterface::class, DraftAfterScanningOrderService::class);
        $this->app->bind(CustomerOrderQueryServiceInterface::class, CustomerOrderQueryService::class);
        $this->app->bind(AdminOrderListQueryServiceInterface::class, AdminOrderListQueryService::class);
        $this->app->bind(AdminOrderDetailQueryServiceInterface::class, AdminOrderDetailQueryService::class);
        $this->app->bind(AdminOrderQueryServiceInterface::class, AdminOrderQueryService::class);
        $this->app->bind(CustomerOrderSubmissionServiceInterface::class, CustomerOrderSubmissionService::class);
        $this->app->bind(ManualOrderSubmissionServiceInterface::class, ManualOrderSubmissionService::class);
        $this->app->bind(FoodOrderAfterSubmitNotifierInterface::class, LaravelFoodOrderAfterSubmitNotifier::class);
        $this->app->bind(FoodOrderReviewNotifierInterface::class, LaravelFoodOrderReviewNotifier::class);
        $this->app->bind(OrderFromCartCreatorInterface::class, OrderFromCartCreator::class);
        $this->app->bind(OrderItemsSnapshotBuilderInterface::class, OrderItemsSnapshotBuilder::class);
        $this->app->bind(ComboPairValidatorInterface::class, ComboPairValidator::class);
        $this->app->bind(OrderCompositionSnapshotBuilderInterface::class, OrderCompositionSnapshotBuilder::class);
        $this->app->bind(OrderCompositionUpdateServiceInterface::class, OrderCompositionUpdateService::class);
        $this->app->bind(OrderStatusResolverInterface::class, OrderStatusResolver::class);
        $this->app->bind(OrderReviewAuthorizationServiceInterface::class, OrderReviewAuthorizationService::class);
        $this->app->bind(OrderReviewCompletionServiceInterface::class, OrderReviewCompletionService::class);
        $this->app->bind(OrderReviewUpdateFactory::class, static function ($app): OrderReviewUpdateFactory {
            return new OrderReviewUpdateFactory(
                $app->make(ClockInterface::class),
                [
                    OrderReviewStep::Address->value => $app->make(AddressOrderReviewUpdateStepHandler::class),
                    OrderReviewStep::Composition->value => $app->make(CompositionOrderReviewUpdateStepHandler::class),
                    OrderReviewStep::Payment->value => $app->make(PaymentOrderReviewUpdateStepHandler::class),
                ],
            );
        });
        $this->app->bind(OrderReviewStepHandlerInterface::class, OrderReviewStepHandler::class);
        $this->app->bind(OrderChatAuthorizationServiceInterface::class, OrderChatAuthorizationService::class);
        $this->app->bind(OrderChatServiceInterface::class, OrderChatService::class);
        $this->app->bind(
            RestaurantRepositoryInterface::class,
            EloquentRestaurantRepository::class,
        );
        $this->app->bind(FoodMoneyFormatterInterface::class, FoodMoneyFormatter::class);
        $this->app->bind(
            MenuReadRepositoryInterface::class,
            EloquentMenuReadRepository::class,
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
            FoodOrderAdminReviewReadRepositoryInterface::class,
            EloquentFoodOrderAdminReviewReadRepository::class,
        );
        $this->app->bind(
            FoodOrderManualAdminReadRepositoryInterface::class,
            EloquentFoodOrderManualAdminReadRepository::class,
        );
        $this->app->bind(
            FoodOrderAdminReadRepositoryInterface::class,
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
        $this->app->bind(
            FoodOrderCustomerStatusMaxMessageBuilderInterface::class,
            FoodOrderCustomerStatusMaxMessageBuilder::class,
        );
        $this->app->bind(
            FoodOrderCustomerCompositionMaxMessageBuilderInterface::class,
            FoodOrderCustomerCompositionChangedMaxMessageBuilder::class,
        );
        $this->app->bind(
            FoodOrderManualCreatorMaxMessageBuilderInterface::class,
            FoodOrderManualOrderCreatorConfirmedMaxMessageBuilder::class,
        );
        $this->app->bind(
            FoodOrderUiStandNewRequestMaxMessageBuilderInterface::class,
            FoodOrderUiStandNewRequestMaxMessageBuilder::class,
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
        $this->app->bind(FoodOrderStatusNotifierInterface::class, LaravelFoodOrderStatusNotifier::class);
        $this->app->bind(FoodOrderCompositionNotifierInterface::class, LaravelFoodOrderCompositionNotifier::class);
        $this->app->bind(FoodOrderManualCreatorNotifierInterface::class, LaravelFoodOrderManualCreatorNotifier::class);
        $this->app->bind(FoodOrderCustomerNotifierInterface::class, LaravelFoodOrderCustomerNotifier::class);
        $this->app->bind(OrderChatNotifierInterface::class, LaravelOrderChatNotifier::class);
    }
}
