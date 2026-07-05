<?php

declare(strict_types=1);

namespace App\Enums\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;

/**
 * Конфигурация этапа проверки заказа: поля БД, роль администратора и область отклонения.
 */
enum OrderReviewStep: string
{
    case Address = 'address';
    case Composition = 'composition';
    case Payment = 'payment';

    public function requiredRole(): FoodOrderAdminRole
    {
        return match ($this) {
            self::Address, self::Payment => FoodOrderAdminRole::AddressReviewer,
            self::Composition => FoodOrderAdminRole::CompositionReviewer,
        };
    }

    public function rejectionScope(): OrderRejectionScope
    {
        return match ($this) {
            self::Address => OrderRejectionScope::Address,
            self::Composition => OrderRejectionScope::Composition,
            self::Payment => OrderRejectionScope::Payment,
        };
    }

    public function statusField(): string
    {
        return match ($this) {
            self::Address => 'address_review_status',
            self::Composition => 'composition_review_status',
            self::Payment => 'payment_review_status',
        };
    }

    public function reviewedByField(): string
    {
        return match ($this) {
            self::Address => 'address_reviewed_by',
            self::Composition => 'composition_reviewed_by',
            self::Payment => 'payment_reviewed_by',
        };
    }

    public function reviewedAtField(): string
    {
        return match ($this) {
            self::Address => 'address_reviewed_at',
            self::Composition => 'composition_reviewed_at',
            self::Payment => 'payment_reviewed_at',
        };
    }

    public function rejectionCommentField(): string
    {
        return match ($this) {
            self::Address => 'address_rejection_comment',
            self::Composition => 'composition_rejection_comment',
            self::Payment => 'payment_rejection_comment',
        };
    }

    public function currentStatus(FoodOrder $order): OrderReviewStatus
    {
        return $order->{$this->statusField()};
    }

    /**
     * @throws FoodDomainException
     */
    public function assertPending(FoodOrder $order): void
    {
        match ($this) {
            self::Address => $this->assertStrictPending(
                $order,
                'Address review already completed.',
                'Order is not awaiting address review.',
            ),
            self::Payment => $this->assertStrictPending(
                $order,
                'Payment review already completed.',
                'Order is not awaiting payment review.',
            ),
            self::Composition => $this->assertCompositionPending($order),
        };
    }

    /**
     * @throws FoodDomainException
     */
    private function assertStrictPending(FoodOrder $order, string $alreadyCompletedMessage, string $notAwaitingMessage): void
    {
        if ($this->currentStatus($order) !== OrderReviewStatus::Pending) {
            throw new FoodDomainException($alreadyCompletedMessage, 422);
        }

        if ($this->isReviewClosed($order->status)) {
            throw new FoodDomainException($notAwaitingMessage, 422);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertCompositionPending(FoodOrder $order): void
    {
        if (! $order->isInCompositionReviewQueue()) {
            throw new FoodDomainException('Composition review already completed.', 422);
        }
    }

    private function isReviewClosed(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::Rejected, OrderStatus::Confirmed], true);
    }
}
