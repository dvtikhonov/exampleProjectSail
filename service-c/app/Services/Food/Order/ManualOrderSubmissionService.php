<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;

/**
 * Ручное оформление заказа менеджером из черновика корзины.
 */
class ManualOrderSubmissionService implements ManualOrderSubmissionServiceInterface
{
    public function __construct(
        private readonly OrderFromCartCreator $orderFromCartCreator,
        private readonly CartDraftRepositoryInterface $cartDraftRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly JobDispatcherInterface $jobDispatcher,
    ) {}

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
            $cart = $this->cartDraftRepository->findManualDraftForUpdate(
                $customer->maxUserId,
                $manager->maxUserId,
            );

            return $this->orderFromCartCreator->create(
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
            $cart = $this->cartDraftRepository->findManualDraftForUpdate(
                $customer->maxUserId,
                $manager->maxUserId,
            );

            return $this->orderFromCartCreator->create(
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
}
