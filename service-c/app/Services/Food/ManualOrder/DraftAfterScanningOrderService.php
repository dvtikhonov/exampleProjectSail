<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\ManualOrder\DraftAfterScanningMoveToCartResultDto;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use Illuminate\Support\Facades\DB;

/**
 * Use-case действия с ручным заказом в статусе «Черновик после сканирования».
 */
class DraftAfterScanningOrderService implements DraftAfterScanningOrderServiceInterface
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function complete(int $orderId, MaxUser $manager): FoodOrder
    {
        $order = DB::transaction(function () use ($orderId, $manager): FoodOrder {
            $order = $this->lockEligibleOrder($orderId);
            $reviewedAt = now();

            return $this->foodOrderWriteRepository->update($order, [
                'status' => OrderStatus::Confirmed,
                'address_review_status' => OrderReviewStatus::Approved,
                'composition_review_status' => OrderReviewStatus::Approved,
                'payment_review_status' => OrderReviewStatus::Approved,
                'address_reviewed_by' => $manager->max_user_id,
                'address_reviewed_at' => $reviewedAt,
                'composition_reviewed_by' => $manager->max_user_id,
                'composition_reviewed_at' => $reviewedAt,
                'payment_reviewed_by' => $manager->max_user_id,
                'payment_reviewed_at' => $reviewedAt,
            ]);
        });

        $this->foodOrderCustomerNotifier->notifyManualOrderCreatorConfirmed($order);

        return $order;
    }

    /**
     * {@inheritDoc}
     */
    public function moveToCart(int $orderId, MaxUser $manager): DraftAfterScanningMoveToCartResultDto
    {
        return DB::transaction(function () use ($orderId, $manager): DraftAfterScanningMoveToCartResultDto {
            $order = $this->lockEligibleOrder($orderId);
            $customer = $this->resolveCustomer($order);
            $lines = $this->cartLinesFromSnapshot($order->items_snapshot ?? []);

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

            $deliveryAddress = $this->normalizedDeliveryAddress($order->delivery_address);

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

            $this->foodOrderWriteRepository->delete($order);

            return new DraftAfterScanningMoveToCartResultDto(
                cart: $cart,
                customerMaxUserId: $customer->max_user_id,
                deliveryAddress: $cart->deliveryAddress,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $orderId, MaxUser $manager): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = $this->lockEligibleOrder($orderId);
            $this->foodOrderWriteRepository->delete($order);
        });
    }

    /**
     * Блокирует ручной заказ и проверяет статус «Черновик после сканирования».
     *
     * @throws FoodDomainException
     */
    private function lockEligibleOrder(int $orderId): FoodOrder
    {
        $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

        if ($order === null || ! $order->is_manual) {
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
     * Клиент ручного заказа.
     *
     * @throws FoodDomainException
     */
    private function resolveCustomer(FoodOrder $order): MaxUser
    {
        $customer = $this->maxUserRepository->findByMaxUserId((int) $order->max_user_id);

        if ($customer === null) {
            throw new FoodDomainException('Клиент заказа не найден.', 422);
        }

        return $customer;
    }

    /**
     * Строки корзины из items_snapshot: dish_id, quantity, combo_ref и партнёр из combo_partner_dish_ids[0].
     *
     * @param  list<array<string, mixed>>|array<int, mixed>  $itemsSnapshot
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
    private function normalizedDeliveryAddress(mixed $deliveryAddress): ?string
    {
        if (! is_string($deliveryAddress)) {
            return null;
        }

        $trimmed = trim($deliveryAddress);

        return $trimmed === '' ? null : $trimmed;
    }
}
