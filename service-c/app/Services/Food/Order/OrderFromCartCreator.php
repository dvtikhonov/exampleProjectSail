<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Cart\CartRecord;
use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Cart\CartTotalsCalculator;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Shared\FoodMoneyFormatter;
use DateTimeInterface;

/**
 * Общее ядро: снимок корзины → заказ → markAsSubmitted.
 */
class OrderFromCartCreator
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly OrderItemsSnapshotBuilder $orderItemsSnapshotBuilder,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly CartLifecycleRepositoryInterface $cartLifecycleRepository,
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly OrderStatusResolver $orderStatusResolver,
        private readonly MenuAvailabilityDateResolverInterface $menuAvailabilityDateResolver,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Создаёт заказ из черновика корзины.
     *
     * @param  string|null  $deliveryDate  явная дата Y-m-d либо null (дата доступности меню)
     * @param  bool  $draftAfterScanning  true — статус draft_after_scanning, адрес может быть пустым
     * @return array{order: FoodOrderRecord, dto: OrderDto}
     *
     * @throws FoodDomainException
     */
    public function create(
        ?CartRecord $cart,
        int $customerMaxUserId,
        bool $isManual,
        ?int $createdByMaxUserId,
        ?string $deliveryDate = null,
        bool $draftAfterScanning = false,
    ): array {
        if ($cart === null || $cart->isEmpty()) {
            throw new FoodDomainException('Корзина пуста.');
        }

        $deliveryAddress = is_string($cart->deliveryAddress) ? trim($cart->deliveryAddress) : '';

        if ($deliveryAddress === '' && ! $draftAfterScanning) {
            throw new FoodDomainException('Укажите адрес доставки.');
        }

        $snapshot = $this->orderItemsSnapshotBuilder->build($cart->items);

        $totals = $this->cartTotalsCalculator->calculate(
            restaurantId: $cart->restaurantId,
            maxUserId: $customerMaxUserId,
            itemsTotal: $snapshot->itemsTotal,
        );

        $formattedItemsTotal = $this->moneyFormatter->format($totals->itemsTotal);
        $formattedDeliveryCost = $totals->deliveryCost !== null
            ? $this->moneyFormatter->format($totals->deliveryCost)
            : null;
        $formattedTotal = $this->moneyFormatter->format($totals->total);

        if ($deliveryAddress !== '') {
            $this->maxUserDeliveryAddressService->persistForMaxUserId($customerMaxUserId, $deliveryAddress);
        }

        $resolvedDeliveryDate = $deliveryDate ?? $this->menuAvailabilityDateResolver->resolve()->date;

        $reviewFields = $this->initialReviewFields($isManual, $createdByMaxUserId, $draftAfterScanning);

        $order = $this->foodOrderWriteRepository->create(new FoodOrderCreateCommand(
            cartId: $cart->id,
            maxUserId: $customerMaxUserId,
            isManual: $isManual,
            createdByMaxUserId: $createdByMaxUserId,
            restaurantId: $cart->restaurantId,
            status: $reviewFields['status'],
            addressReviewStatus: $reviewFields['addressReviewStatus'],
            compositionReviewStatus: $reviewFields['compositionReviewStatus'],
            paymentReviewStatus: $reviewFields['paymentReviewStatus'],
            addressReviewedBy: $reviewFields['addressReviewedBy'],
            addressReviewedAt: $reviewFields['addressReviewedAt'],
            compositionReviewedBy: $reviewFields['compositionReviewedBy'],
            compositionReviewedAt: $reviewFields['compositionReviewedAt'],
            paymentReviewedBy: $reviewFields['paymentReviewedBy'],
            paymentReviewedAt: $reviewFields['paymentReviewedAt'],
            total: $formattedTotal,
            deliveryAddress: $deliveryAddress,
            deliveryDate: $resolvedDeliveryDate,
            deliveryCost: $formattedDeliveryCost,
            itemsTotal: $formattedItemsTotal,
            itemsSnapshot: $snapshot->itemsSnapshot,
        ));

        $this->cartLifecycleRepository->markAsSubmitted($cart->id);

        return [
            'order' => $order,
            'dto' => new OrderDto(
                id: $order->id,
                status: $order->status->value,
                restaurantId: $order->restaurantId,
                restaurantName: (string) ($cart->restaurantName ?? ''),
                itemsTotal: $formattedItemsTotal,
                deliveryApplicable: $totals->deliveryApplicable,
                deliveryCost: $formattedDeliveryCost,
                total: $formattedTotal,
                deliveryAddress: $deliveryAddress !== '' ? $deliveryAddress : null,
                deliveryDate: $resolvedDeliveryDate,
                itemsSnapshot: $snapshot->itemsSnapshot,
                createdAt: $order->createdAt,
            ),
        ];
    }

    /**
     * Начальные статусы: клиентский — pending_review; черновик скана — draft_after_scanning;
     * ручной submit — сразу approved/confirmed.
     *
     * @return array{
     *     status: OrderStatus,
     *     addressReviewStatus: OrderReviewStatus,
     *     compositionReviewStatus: OrderReviewStatus,
     *     paymentReviewStatus: OrderReviewStatus,
     *     addressReviewedBy: ?int,
     *     addressReviewedAt: ?string,
     *     compositionReviewedBy: ?int,
     *     compositionReviewedAt: ?string,
     *     paymentReviewedBy: ?int,
     *     paymentReviewedAt: ?string,
     * }
     */
    private function initialReviewFields(
        bool $isManual,
        ?int $createdByMaxUserId,
        bool $draftAfterScanning,
    ): array {
        if ($draftAfterScanning) {
            return [
                'status' => OrderStatus::DraftAfterScanning,
                'addressReviewStatus' => OrderReviewStatus::Pending,
                'compositionReviewStatus' => OrderReviewStatus::Pending,
                'paymentReviewStatus' => OrderReviewStatus::Pending,
                'addressReviewedBy' => null,
                'addressReviewedAt' => null,
                'compositionReviewedBy' => null,
                'compositionReviewedAt' => null,
                'paymentReviewedBy' => null,
                'paymentReviewedAt' => null,
            ];
        }

        if (! $isManual) {
            return [
                'status' => OrderStatus::PendingReview,
                'addressReviewStatus' => OrderReviewStatus::Pending,
                'compositionReviewStatus' => OrderReviewStatus::Pending,
                'paymentReviewStatus' => OrderReviewStatus::Pending,
                'addressReviewedBy' => null,
                'addressReviewedAt' => null,
                'compositionReviewedBy' => null,
                'compositionReviewedAt' => null,
                'paymentReviewedBy' => null,
                'paymentReviewedAt' => null,
            ];
        }

        $approved = OrderReviewStatus::Approved;
        $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);

        return [
            'status' => $this->orderStatusResolver->resolve($approved, $approved, $approved),
            'addressReviewStatus' => $approved,
            'compositionReviewStatus' => $approved,
            'paymentReviewStatus' => $approved,
            'addressReviewedBy' => $createdByMaxUserId,
            'addressReviewedAt' => $reviewedAt,
            'compositionReviewedBy' => $createdByMaxUserId,
            'compositionReviewedAt' => $reviewedAt,
            'paymentReviewedBy' => $createdByMaxUserId,
            'paymentReviewedAt' => $reviewedAt,
        ];
    }
}
