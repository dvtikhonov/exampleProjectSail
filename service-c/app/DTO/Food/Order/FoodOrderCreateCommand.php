<?php

declare(strict_types=1);

namespace App\DTO\Food\Order;

use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Команда создания заказа еды для write-репозитория.
 */
readonly class FoodOrderCreateCommand
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function __construct(
        public int $cartId,
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
        public ?int $paymentReviewedBy,
        public ?string $paymentReviewedAt,
        public string $total,
        public ?string $deliveryAddress,
        public ?string $deliveryDate,
        public ?string $deliveryCost,
        public string $itemsTotal,
        public array $itemsSnapshot,
    ) {}
}
