<?php

declare(strict_types=1);

namespace App\DTO\Food\Order;

use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Доменная проекция заказа еды без зависимостей от Eloquent.
 */
readonly class FoodOrderRecord
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function __construct(
        public int $id,
        public ?int $cartId,
        public int $maxUserId,
        public bool $isManual,
        public ?int $createdByMaxUserId,
        public int $restaurantId,
        public OrderStatus $status,
        public OrderReviewStatus $addressReviewStatus,
        public OrderReviewStatus $compositionReviewStatus,
        public OrderReviewStatus $paymentReviewStatus,
        public ?int $addressReviewedBy,
        public ?string $addressReviewedAt,
        public ?int $compositionReviewedBy,
        public ?string $compositionReviewedAt,
        public ?string $addressRejectionComment,
        public ?string $compositionRejectionComment,
        public ?int $paymentReviewedBy,
        public ?string $paymentReviewedAt,
        public ?string $paymentRejectionComment,
        public string $total,
        public ?string $deliveryAddress,
        public ?string $deliveryDate,
        public ?string $deliveryCost,
        public string $itemsTotal,
        public array $itemsSnapshot,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $restaurantName = null,
        public ?string $customerFirstName = null,
        public ?string $customerLastName = null,
        public ?string $customerUsername = null,
        public ?bool $hasMessages = null,
    ) {}

    /**
     * Заказ ожидает проверки состава (включая legacy-записи с not_applicable).
     */
    public function isInCompositionReviewQueue(): bool
    {
        if (in_array($this->status, [OrderStatus::Rejected, OrderStatus::Confirmed], true)) {
            return false;
        }

        return $this->compositionReviewStatus === OrderReviewStatus::Pending
            || $this->compositionReviewStatus === OrderReviewStatus::NotApplicable;
    }
}
