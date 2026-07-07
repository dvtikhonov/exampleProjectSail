<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\OrderSubmissionServiceInterface;
use App\DTO\Food\OrderDto;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Support\Facades\DB;

/**
 * Оформление заказа из черновика корзины и уведомление MAX.
 */
class OrderSubmissionService implements OrderSubmissionServiceInterface
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly OrderItemsSnapshotBuilder $orderItemsSnapshotBuilder,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly FoodOrderMaxNotifierInterface $foodOrderMaxNotifier,
    ) {}

    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUser $maxUser): OrderDto
    {
        $orderDto = DB::transaction(function () use ($maxUser): OrderDto {
            $cart = $this->cartRepository->findDraftForUpdate($maxUser->max_user_id);

            if ($cart === null || $cart->items->isEmpty()) {
                throw new FoodDomainException('Cart is empty.');
            }

            if ($cart->delivery_address === null || trim($cart->delivery_address) === '') {
                throw new FoodDomainException('Укажите адрес доставки.');
            }

            $snapshot = $this->orderItemsSnapshotBuilder->build($cart->items);

            $totals = $this->cartTotalsCalculator->calculate(
                restaurantId: $cart->restaurant_id,
                maxUser: $maxUser,
                itemsTotal: $snapshot->itemsTotal,
            );

            $formattedItemsTotal = $this->moneyFormatter->format($totals->itemsTotal);
            $formattedDeliveryCost = $totals->deliveryCost !== null
                ? $this->moneyFormatter->format($totals->deliveryCost)
                : null;
            $formattedTotal = $this->moneyFormatter->format($totals->total);

            $this->maxUserDeliveryAddressService->persist($maxUser, $cart->delivery_address);

            $order = $this->foodOrderWriteRepository->create([
                'cart_id' => $cart->id,
                'max_user_id' => $maxUser->max_user_id,
                'restaurant_id' => $cart->restaurant_id,
                'status' => OrderStatus::PendingReview,
                'address_review_status' => OrderReviewStatus::Pending,
                'composition_review_status' => OrderReviewStatus::Pending,
                'payment_review_status' => OrderReviewStatus::Pending,
                'total' => $formattedTotal,
                'delivery_address' => $cart->delivery_address,
                'delivery_cost' => $formattedDeliveryCost,
                'items_total' => $formattedItemsTotal,
                'items_snapshot' => $snapshot->itemsSnapshot,
            ]);
            $order->refresh();

            $this->cartRepository->markAsSubmitted($cart);

            return new OrderDto(
                id: $order->id,
                status: $order->status->value,
                restaurantId: $order->restaurant_id,
                restaurantName: $cart->restaurant->name,
                itemsTotal: $formattedItemsTotal,
                deliveryApplicable: $totals->deliveryApplicable,
                deliveryCost: $formattedDeliveryCost,
                total: $formattedTotal,
                deliveryAddress: $cart->delivery_address,
                itemsSnapshot: $order->items_snapshot ?? [],
                createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
            );
        });

        $this->foodOrderMaxNotifier->notify($orderDto, $maxUser);

        return $orderDto;
    }
}
