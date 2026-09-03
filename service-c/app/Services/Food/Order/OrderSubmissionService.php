<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Order\OrderSubmissionServiceInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\RequestTimingRecorderInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartRecord;
use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;
use App\Services\Food\Cart\CartTotalsCalculator;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Shared\FoodMoneyFormatter;
use App\Support\Profiling\OrderSubmitTiming;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

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
        private readonly TransactionManagerInterface $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly JobDispatcherInterface $jobDispatcher,
        private readonly RequestTimingRecorderInterface $requestTimingRecorder,
    ) {}

    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUserIdentity $user): OrderDto
    {
        $submitStartedAt = hrtime(true);

        /** @var array{order: FoodOrderRecord, dto: OrderDto} $result */
        $txStartedAt = hrtime(true);
        $result = $this->transactionManager->run(function () use ($user): array {
            $cart = $this->cartRepository->findDraftForUpdate($user->maxUserId);

            return $this->createOrderFromCart(
                cart: $cart,
                customerMaxUserId: $user->maxUserId,
                isManual: false,
                createdByMaxUserId: null,
                draftAfterScanning: false,
            );
        });
        // tTxMs — длительность транзакции: снимок корзины, создание заказа, markAsSubmitted.
        $tTxMs = $this->elapsedMs($txStartedAt);

        $notifyStartedAt = hrtime(true);
        $this->dispatchAfterSubmitNotify(
            order: $result['order'],
            dto: $result['dto'],
            maxUserId: $user->maxUserId,
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
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        ?string $deliveryDate = null,
    ): OrderDto {
        $normalizedDeliveryDate = $this->normalizeDeliveryDate($deliveryDate);

        /** @var array{order: FoodOrderRecord, dto: OrderDto} $result */
        $result = $this->transactionManager->run(function () use ($customer, $manager, $normalizedDeliveryDate): array {
            $cart = $this->cartRepository->findManualDraftForUpdate(
                $customer->maxUserId,
                $manager->maxUserId,
            );

            return $this->createOrderFromCart(
                cart: $cart,
                customerMaxUserId: $customer->maxUserId,
                isManual: true,
                createdByMaxUserId: $manager->maxUserId,
                deliveryDate: $normalizedDeliveryDate,
                draftAfterScanning: false,
            );
        });

        $this->dispatchAfterSubmitNotify(
            order: $result['order'],
            dto: $result['dto'],
            maxUserId: $customer->maxUserId,
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
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        ?string $deliveryDate = null,
    ): OrderDto {
        $normalizedDeliveryDate = $this->normalizeDeliveryDate($deliveryDate);

        /** @var array{order: FoodOrderRecord, dto: OrderDto} $result */
        $result = $this->transactionManager->run(function () use ($customer, $manager, $normalizedDeliveryDate): array {
            $cart = $this->cartRepository->findManualDraftForUpdate(
                $customer->maxUserId,
                $manager->maxUserId,
            );

            return $this->createOrderFromCart(
                cart: $cart,
                customerMaxUserId: $customer->maxUserId,
                isManual: true,
                createdByMaxUserId: $manager->maxUserId,
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
        FoodOrderRecord $order,
        OrderDto $dto,
        int $maxUserId,
        FoodOrderAfterSubmitNotifyKind $kind,
    ): void {
        $this->jobDispatcher->dispatch(new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUserId,
            kind: $kind,
        ));
    }

    /**
     * Создаёт заказ из черновика корзины.
     *
     * @param  string|null  $deliveryDate  явная дата Y-m-d либо null (дата доступности меню)
     * @param  bool  $draftAfterScanning  true — статус draft_after_scanning, адрес может быть пустым
     * @return array{order: FoodOrderRecord, dto: OrderDto}
     *
     * @throws FoodDomainException
     */
    private function createOrderFromCart(
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

        $this->cartRepository->markAsSubmitted($cart->id);

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
     * Пишет профилирование submit в лог и (при HTTP) в атрибут запроса для Server-Timing.
     *
     * @param  float  $tTxMs  мс транзакции (корзина → заказ)
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

        $this->logger->info('order.submit.profile', $timing);

        $this->requestTimingRecorder->record(OrderSubmitTiming::REQUEST_ATTRIBUTE, $timing);
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
