<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Order\OrderSubmissionServiceInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use App\Services\Food\Cart\CartTotalsCalculator;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Shared\FoodMoneyFormatter;
use App\Support\Profiling\OrderSubmitTiming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Оформление заказа из черновика корзины и уведомление MAX.
 */
class OrderSubmissionService implements OrderSubmissionServiceInterface
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly OrderItemsSnapshotBuilder $orderItemsSnapshotBuilder,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly OrderStatusResolver $orderStatusResolver,
        private readonly MenuAvailabilityDateResolverInterface $menuAvailabilityDateResolver,
    ) {}

    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUser $maxUser): OrderDto
    {
        $submitStartedAt = hrtime(true);

        /** @var array{order: FoodOrder, dto: OrderDto} $result */
        $txStartedAt = hrtime(true);
        $result = DB::transaction(function () use ($maxUser): array {
            $cart = $this->cartRepository->findDraftForUpdate($maxUser->max_user_id);

            return $this->createOrderFromCart(
                cart: $cart,
                customer: $maxUser,
                isManual: false,
                createdByMaxUserId: null,
                draftAfterScanning: false,
            );
        });
        // tTxMs — длительность DB::transaction: снимок корзины, создание заказа, markAsSubmitted.
        $tTxMs = $this->elapsedMs($txStartedAt);

        $notifyStartedAt = hrtime(true);
        $this->dispatchAfterSubmitNotify(
            order: $result['order'],
            dto: $result['dto'],
            maxUserId: $maxUser->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );
        // tNotifyMs — постановка NotifyFoodOrderAfterSubmitJob в очередь (после commit, без ожидания MAX API).
        $tNotifyMs = $this->elapsedMs($notifyStartedAt);
        // tSubmitMs — полное время submit(): транзакция + dispatch уведомления.
        $tSubmitMs = $this->elapsedMs($submitStartedAt);

        $this->recordSubmitTiming($tTxMs, $tNotifyMs, $tSubmitMs);

        return $result['dto'];
    }

    /**
     * {@inheritDoc}
     *
     * Ручной заказ сразу проходит все этапы проверки (approved) и становится confirmed.
     */
    public function submitManual(
        MaxUser $customer,
        MaxUser $manager,
        ?string $deliveryDate = null,
    ): OrderDto {
        $normalizedDeliveryDate = $this->normalizeDeliveryDate($deliveryDate);

        /** @var array{order: FoodOrder, dto: OrderDto} $result */
        $result = DB::transaction(function () use ($customer, $manager, $normalizedDeliveryDate): array {
            $cart = $this->cartRepository->findManualDraftForUpdate(
                $customer->max_user_id,
                $manager->max_user_id,
            );

            return $this->createOrderFromCart(
                cart: $cart,
                customer: $customer,
                isManual: true,
                createdByMaxUserId: $manager->max_user_id,
                deliveryDate: $normalizedDeliveryDate,
                draftAfterScanning: false,
            );
        });

        $this->dispatchAfterSubmitNotify(
            order: $result['order'],
            dto: $result['dto'],
            maxUserId: $customer->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Confirmed,
        );

        return $result['dto'];
    }

    /**
     * {@inheritDoc}
     *
     * Черновик после сканирования: is_manual, статус draft_after_scanning, этапы проверки pending.
     */
    public function submitDraftAfterScanning(
        MaxUser $customer,
        MaxUser $manager,
        ?string $deliveryDate = null,
    ): OrderDto {
        $normalizedDeliveryDate = $this->normalizeDeliveryDate($deliveryDate);

        /** @var array{order: FoodOrder, dto: OrderDto} $result */
        $result = DB::transaction(function () use ($customer, $manager, $normalizedDeliveryDate): array {
            $cart = $this->cartRepository->findManualDraftForUpdate(
                $customer->max_user_id,
                $manager->max_user_id,
            );

            return $this->createOrderFromCart(
                cart: $cart,
                customer: $customer,
                isManual: true,
                createdByMaxUserId: $manager->max_user_id,
                deliveryDate: $normalizedDeliveryDate,
                draftAfterScanning: true,
            );
        });

        return $result['dto'];
    }

    /**
     * Ставит в очередь MAX-уведомления после commit транзакции оформления.
     */
    private function dispatchAfterSubmitNotify(
        FoodOrder $order,
        OrderDto $dto,
        int $maxUserId,
        FoodOrderAfterSubmitNotifyKind $kind,
    ): void {
        NotifyFoodOrderAfterSubmitJob::dispatch(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUserId,
            kind: $kind,
        );
    }

    /**
     * Создаёт заказ из черновика корзины.
     *
     * @param  string|null  $deliveryDate  явная дата Y-m-d либо null (дата доступности меню)
     * @param  bool  $draftAfterScanning  true — статус draft_after_scanning, адрес может быть пустым
     * @return array{order: FoodOrder, dto: OrderDto}
     *
     * @throws FoodDomainException
     */
    private function createOrderFromCart(
        ?Cart $cart,
        MaxUser $customer,
        bool $isManual,
        ?int $createdByMaxUserId,
        ?string $deliveryDate = null,
        bool $draftAfterScanning = false,
    ): array {
        if ($cart === null || $cart->items->isEmpty()) {
            throw new FoodDomainException('Корзина пуста.');
        }

        $deliveryAddress = is_string($cart->delivery_address) ? trim($cart->delivery_address) : '';

        if ($deliveryAddress === '' && ! $draftAfterScanning) {
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

        if ($deliveryAddress !== '') {
            $this->maxUserDeliveryAddressService->persist($customer, $deliveryAddress);
        }

        $resolvedDeliveryDate = $deliveryDate ?? $this->menuAvailabilityDateResolver->resolve()->date;

        $order = $this->foodOrderWriteRepository->create([
            'cart_id' => $cart->id,
            'max_user_id' => $customer->max_user_id,
            'is_manual' => $isManual,
            'created_by_max_user_id' => $createdByMaxUserId,
            'restaurant_id' => $cart->restaurant_id,
            ...$this->initialReviewAttributes($isManual, $createdByMaxUserId, $draftAfterScanning),
            'total' => $formattedTotal,
            'delivery_address' => $deliveryAddress,
            'delivery_date' => $resolvedDeliveryDate,
            'delivery_cost' => $formattedDeliveryCost,
            'items_total' => $formattedItemsTotal,
            'items_snapshot' => $snapshot->itemsSnapshot,
        ]);

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
                deliveryAddress: $deliveryAddress !== '' ? $deliveryAddress : null,
                deliveryDate: $resolvedDeliveryDate,
                itemsSnapshot: $snapshot->itemsSnapshot,
                createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ),
        ];
    }

    /**
     * Пишет профилирование submit в лог и (при HTTP) в атрибут запроса для Server-Timing.
     *
     * @param  float  $tTxMs  мс DB::transaction (корзина → заказ)
     * @param  float  $tNotifyMs  мс dispatch NotifyFoodOrderAfterSubmitJob (не время доставки в MAX)
     * @param  float  $tSubmitMs  мс всего submit() = tx + notify
     */
    private function recordSubmitTiming(float $tTxMs, float $tNotifyMs, float $tSubmitMs): void
    {
        $timing = [
            't_tx_ms' => round($tTxMs, 1),
            't_notify_ms' => round($tNotifyMs, 1),
            't_submit_ms' => round($tSubmitMs, 1),
        ];

        Log::info('order.submit.profile', $timing);

        if (! app()->bound('request')) {
            return;
        }

        request()->attributes->set(OrderSubmitTiming::REQUEST_ATTRIBUTE, $timing);
    }

    /**
     * Пустая строка трактуется как отсутствие явной даты доставки.
     */
    private function normalizeDeliveryDate(?string $deliveryDate): ?string
    {
        if ($deliveryDate === null) {
            return null;
        }

        $normalized = trim($deliveryDate);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  int  $startedAtHrtime  значение hrtime(true)
     */
    private function elapsedMs(int $startedAtHrtime): float
    {
        return (hrtime(true) - $startedAtHrtime) / 1_000_000;
    }

    /**
     * Начальные статусы: клиентский — pending_review; черновик скана — draft_after_scanning;
     * ручной submit — сразу approved/confirmed.
     *
     * @return array<string, mixed>
     */
    private function initialReviewAttributes(
        bool $isManual,
        ?int $createdByMaxUserId,
        bool $draftAfterScanning,
    ): array {
        if ($draftAfterScanning) {
            return [
                'status' => OrderStatus::DraftAfterScanning,
                'address_review_status' => OrderReviewStatus::Pending,
                'composition_review_status' => OrderReviewStatus::Pending,
                'payment_review_status' => OrderReviewStatus::Pending,
            ];
        }

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
