<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\OrderSubmissionServiceInterface;
use App\DTO\Food\OrderDto;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\FoodOrder;
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
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
        private readonly OrderStatusResolver $orderStatusResolver,
    ) {}

    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUser $maxUser): OrderDto
    {
        /** @var array{order: FoodOrder, dto: OrderDto} $result */
        $result = DB::transaction(function () use ($maxUser): array {
            $cart = $this->cartRepository->findDraftForUpdate($maxUser->max_user_id);

            return $this->createOrderFromCart(
                cart: $cart,
                customer: $maxUser,
                isManual: false,
                createdByMaxUserId: null,
            );
        });

        $this->foodOrderMaxNotifier->notify($result['dto'], $maxUser);
        $this->foodOrderCustomerNotifier->notifySubmitted($result['order']);

        return $result['dto'];
    }

    /**
     * {@inheritDoc}
     *
     * Ручной заказ сразу проходит все этапы проверки (approved) и становится confirmed.
     */
    public function submitManual(MaxUser $customer, MaxUser $manager): OrderDto
    {
        /** @var array{order: FoodOrder, dto: OrderDto} $result */
        $result = DB::transaction(function () use ($customer, $manager): array {
            $cart = $this->cartRepository->findManualDraftForUpdate(
                $customer->max_user_id,
                $manager->max_user_id,
            );

            return $this->createOrderFromCart(
                cart: $cart,
                customer: $customer,
                isManual: true,
                createdByMaxUserId: $manager->max_user_id,
            );
        });

        $this->foodOrderMaxNotifier->notify($result['dto'], $customer);
        $this->foodOrderCustomerNotifier->notifyConfirmed($result['order']);

        return $result['dto'];
    }

    /**
     * Создаёт заказ из черновика корзины.
     *
     * @return array{order: FoodOrder, dto: OrderDto}
     *
     * @throws FoodDomainException
     */
    private function createOrderFromCart(
        ?Cart $cart,
        MaxUser $customer,
        bool $isManual,
        ?int $createdByMaxUserId,
    ): array {
        if ($cart === null || $cart->items->isEmpty()) {
            throw new FoodDomainException('Cart is empty.');
        }

        if ($cart->delivery_address === null || trim($cart->delivery_address) === '') {
            throw new FoodDomainException('Укажите адрес доставки.');
        }

        $snapshot = $this->orderItemsSnapshotBuilder->build($cart->items);

        $totals = $this->cartTotalsCalculator->calculate(
            restaurantId: $cart->restaurant_id,
            maxUser: $customer,
            itemsTotal: $snapshot->itemsTotal,
        );

        $formattedItemsTotal = $this->moneyFormatter->format($totals->itemsTotal);
        $formattedDeliveryCost = $totals->deliveryCost !== null
            ? $this->moneyFormatter->format($totals->deliveryCost)
            : null;
        $formattedTotal = $this->moneyFormatter->format($totals->total);

        $this->maxUserDeliveryAddressService->persist($customer, $cart->delivery_address);

        $order = $this->foodOrderWriteRepository->create([
            'cart_id' => $cart->id,
            'max_user_id' => $customer->max_user_id,
            'is_manual' => $isManual,
            'created_by_max_user_id' => $createdByMaxUserId,
            'restaurant_id' => $cart->restaurant_id,
            ...$this->initialReviewAttributes($isManual, $createdByMaxUserId),
            'total' => $formattedTotal,
            'delivery_address' => $cart->delivery_address,
            'delivery_cost' => $formattedDeliveryCost,
            'items_total' => $formattedItemsTotal,
            'items_snapshot' => $snapshot->itemsSnapshot,
        ]);
        $order->refresh();

        $this->cartRepository->markAsSubmitted($cart);

        return [
            'order' => $order,
            'dto' => new OrderDto(
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
            ),
        ];
    }

    /**
     * Начальные статусы проверки: клиентский заказ — pending; ручной — сразу approved/confirmed.
     *
     * @return array<string, mixed>
     */
    private function initialReviewAttributes(bool $isManual, ?int $createdByMaxUserId): array
    {
        if (! $isManual) {
            return [
                'status' => OrderStatus::PendingReview,
                'address_review_status' => OrderReviewStatus::Pending,
                'composition_review_status' => OrderReviewStatus::Pending,
                'payment_review_status' => OrderReviewStatus::Pending,
            ];
        }

        $approved = OrderReviewStatus::Approved;
        $reviewedAt = now();

        return [
            'status' => $this->orderStatusResolver->resolve($approved, $approved, $approved),
            'address_review_status' => $approved,
            'composition_review_status' => $approved,
            'payment_review_status' => $approved,
            'address_reviewed_by' => $createdByMaxUserId,
            'address_reviewed_at' => $reviewedAt,
            'composition_reviewed_by' => $createdByMaxUserId,
            'composition_reviewed_at' => $reviewedAt,
            'payment_reviewed_by' => $createdByMaxUserId,
            'payment_reviewed_at' => $reviewedAt,
        ];
    }
}
