<?php

declare(strict_types=1);

namespace App\Enums\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Конфигурация этапа проверки заказа: роль администратора, область отклонения и проверка pending.
 *
 * Имена колонок БД живут только в persistence (FoodOrderMapper / admin-read repository).
 */
enum OrderReviewStep: string
{
    case Address = 'address';
    case Composition = 'composition';
    case Payment = 'payment';

    /**
     * Роль администратора, необходимая для этого этапа.
     */
    public function requiredRole(): FoodOrderAdminRole
    {
        return match ($this) {
            self::Address, self::Payment => FoodOrderAdminRole::AddressReviewer,
            self::Composition => FoodOrderAdminRole::CompositionReviewer,
        };
    }

    /**
     * Область отклонения, соответствующая этапу.
     */
    public function rejectionScope(): OrderRejectionScope
    {
        return match ($this) {
            self::Address => OrderRejectionScope::Address,
            self::Composition => OrderRejectionScope::Composition,
            self::Payment => OrderRejectionScope::Payment,
        };
    }

    /**
     * Текущий статус этапа на проекции заказа.
     */
    public function currentStatus(FoodOrderRecord $order): OrderReviewStatus
    {
        return match ($this) {
            self::Address => $order->addressReviewStatus,
            self::Composition => $order->compositionReviewStatus,
            self::Payment => $order->paymentReviewStatus,
        };
    }

    /**
     * Убеждается, что этап ещё ожидает проверки.
     *
     * @throws FoodDomainException
     */
    public function assertPending(FoodOrderRecord $order): void
    {
        match ($this) {
            self::Address => $this->assertStrictPending(
                $order,
                'Проверка адреса уже завершена.',
                'Заказ не ожидает проверки адреса.',
            ),
            self::Payment => $this->assertStrictPending(
                $order,
                'Проверка оплаты уже завершена.',
                'Заказ не ожидает проверки оплаты.',
            ),
            self::Composition => $this->assertCompositionPending($order),
        };
    }

    /**
     * Строгая проверка pending для адреса/оплаты с учётом закрытого заказа.
     *
     * @throws FoodDomainException
     */
    private function assertStrictPending(FoodOrderRecord $order, string $alreadyCompletedMessage, string $notAwaitingMessage): void
    {
        if ($this->currentStatus($order) !== OrderReviewStatus::Pending) {
            throw new FoodDomainException($alreadyCompletedMessage, 422);
        }

        if ($this->isReviewClosed($order->status)) {
            throw new FoodDomainException($notAwaitingMessage, 422);
        }
    }

    /**
     * Проверка, что заказ ещё в очереди проверки состава.
     *
     * @throws FoodDomainException
     */
    private function assertCompositionPending(FoodOrderRecord $order): void
    {
        if (! $order->isInCompositionReviewQueue()) {
            throw new FoodDomainException('Проверка состава уже завершена.', 422);
        }
    }

    /**
     * Заказ уже подтверждён или отклонён — этапы проверки закрыты.
     */
    private function isReviewClosed(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::Rejected, OrderStatus::Confirmed], true);
    }
}
