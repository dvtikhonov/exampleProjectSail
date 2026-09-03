<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\ManualOrder\DraftAfterScanningMoveToCartResultDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use DateTimeInterface;

/**
 * Use-case действия с ручным заказом в статусе «Черновик после сканирования».
 */
class DraftAfterScanningOrderService implements DraftAfterScanningOrderServiceInterface
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function complete(int $orderId, MaxUserIdentity $manager): FoodOrderRecord
    {
        $order = $this->transactionManager->run(function () use ($orderId, $manager): FoodOrderRecord {
            $order = $this->lockEligibleOrder($orderId);
            $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);

            return $this->foodOrderWriteRepository->update($order, new FoodOrderUpdateCommand(
                status: OrderStatus::Confirmed,
                addressReviewStatus: OrderReviewStatus::Approved,
                compositionReviewStatus: OrderReviewStatus::Approved,
                paymentReviewStatus: OrderReviewStatus::Approved,
                addressReviewedBy: $manager->maxUserId,
                addressReviewedAt: $reviewedAt,
                compositionReviewedBy: $manager->maxUserId,
                compositionReviewedAt: $reviewedAt,
                paymentReviewedBy: $manager->maxUserId,
                paymentReviewedAt: $reviewedAt,
            ));
        });

        $this->foodOrderCustomerNotifier->notifyManualOrderCreatorConfirmed($order);

        return $order;
    }

    /**
     * {@inheritDoc}
     */
    public function moveToCart(int $orderId, MaxUserIdentity $manager): DraftAfterScanningMoveToCartResultDto
    {
        return $this->transactionManager->run(function () use ($orderId, $manager): DraftAfterScanningMoveToCartResultDto {
            $order = $this->lockEligibleOrder($orderId);
            $customer = new MaxUserIdentity($order->maxUserId, []);
            $lines = $this->cartLinesFromSnapshot($order->itemsSnapshot);

            if ($lines === []) {
                throw new FoodDomainException('В заказе нет позиций для переноса в корзину.');
            }

            $this->manualOrderCartService->clear($customer, $manager);

            $cart = null;

            foreach ($lines as $line) {
                $cart = $this->manualOrderCartService->addItem(
                    $customer,
                    $manager,
                    $line['dish_id'],
                    $line['quantity'],
                    $line['combo_ref'],
                    $line['combo_partner_dish_id'],
                );
            }

            $deliveryAddress = $this->normalizedDeliveryAddress($order->deliveryAddress);

            if ($deliveryAddress !== null) {
                $updatedCart = $this->manualOrderCartService->updateDeliveryAddress(
                    $customer,
                    $manager,
                    $deliveryAddress,
                );

                if ($updatedCart !== null) {
                    $cart = $updatedCart;
                }
            }

            if (! $cart instanceof CartDto) {
                throw new FoodDomainException('Не удалось сформировать ручную корзину из заказа.');
            }

            $orderDeliveryDate = $order->deliveryDate;
            $cart = $cart->withDeliveryDate($orderDeliveryDate);

            $this->foodOrderWriteRepository->delete($order);

            return new DraftAfterScanningMoveToCartResultDto(
                cart: $cart,
                customerMaxUserId: $order->maxUserId,
                deliveryAddress: $cart->deliveryAddress,
                deliveryDate: $orderDeliveryDate,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $orderId, MaxUserIdentity $manager): void
    {
        $this->transactionManager->run(function () use ($orderId): void {
            $order = $this->lockEligibleOrder($orderId);
            $this->foodOrderWriteRepository->delete($order);
        });
    }

    /**
     * Блокирует ручной заказ и проверяет статус «Черновик после сканирования».
     *
     * @throws FoodDomainException
     */
    private function lockEligibleOrder(int $orderId): FoodOrderRecord
    {
        $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

        if ($order === null || ! $order->isManual) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        if ($order->status !== OrderStatus::DraftAfterScanning) {
            throw new FoodDomainException(
                'Действие доступно только для заказа в статусе «Черновик после сканирования».',
                422,
            );
        }

        return $order;
    }

    /**
     * Строки корзины из items_snapshot: dish_id, quantity, combo_ref и партнёр из combo_partner_dish_ids[0].
     *
     * @param  list<array<string, mixed>>  $itemsSnapshot
     * @return list<array{dish_id: int, quantity: int, combo_ref: string|null, combo_partner_dish_id: int|null}>
     *
     * @throws FoodDomainException
     */
    private function cartLinesFromSnapshot(array $itemsSnapshot): array
    {
        $lines = [];

        foreach ($itemsSnapshot as $item) {
            if (! is_array($item)) {
                throw new FoodDomainException('Некорректная позиция в составе заказа.');
            }

            $dishId = isset($item['dish_id']) ? (int) $item['dish_id'] : 0;
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;

            if ($dishId < 1 || $quantity < 1) {
                throw new FoodDomainException('Некорректная позиция в составе заказа.');
            }

            $comboRef = isset($item['combo_ref']) && is_string($item['combo_ref']) && $item['combo_ref'] !== ''
                ? $item['combo_ref']
                : null;

            $partnerIds = $item['combo_partner_dish_ids'] ?? [];
            $partnerDishId = null;

            if ($comboRef !== null && is_array($partnerIds) && $partnerIds !== []) {
                $firstPartnerId = $partnerIds[0] ?? null;

                if (is_numeric($firstPartnerId) && (int) $firstPartnerId > 0) {
                    $partnerDishId = (int) $firstPartnerId;
                }
            }

            if ($comboRef === null || $partnerDishId === null) {
                $comboRef = null;
                $partnerDishId = null;
            }

            $lines[] = [
                'dish_id' => $dishId,
                'quantity' => $quantity,
                'combo_ref' => $comboRef,
                'combo_partner_dish_id' => $partnerDishId,
            ];
        }

        return $lines;
    }

    /**
     * Нормализованный адрес доставки заказа или null, если пустой.
     */
    private function normalizedDeliveryAddress(?string $deliveryAddress): ?string
    {
        if ($deliveryAddress === null) {
            return null;
        }

        $trimmed = trim($deliveryAddress);

        return $trimmed === '' ? null : $trimmed;
    }
}
