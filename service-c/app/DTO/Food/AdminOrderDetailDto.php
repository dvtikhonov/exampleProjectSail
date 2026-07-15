<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Полные данные заказа для экрана проверки администратором.
 */
readonly class AdminOrderDetailDto
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function __construct(
        public int $id,
        public string $status,
        public int $restaurantId,
        public string $restaurantName,
        public int $customerMaxUserId,
        public ?string $customerFirstName,
        public ?string $customerLastName,
        public ?string $customerUsername,
        public ?string $deliveryAddress,
        public string $itemsTotal,
        public ?string $deliveryCost,
        public string $total,
        public array $itemsSnapshot,
        public string $addressReviewStatus,
        public string $compositionReviewStatus,
        public string $paymentReviewStatus,
        public ?int $addressReviewedBy,
        public ?string $addressReviewedAt,
        public ?string $addressRejectionComment,
        public ?int $compositionReviewedBy,
        public ?string $compositionReviewedAt,
        public ?string $compositionRejectionComment,
        public ?int $paymentReviewedBy,
        public ?string $paymentReviewedAt,
        public ?string $paymentRejectionComment,
        public string $createdAt,
    ) {}

    /**
     * Преобразует детальный админский заказ в массив.
     *
     * @return array<string, bool|int|string|null|list<array<string, mixed>>|array<string, int|string|null>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'customer' => [
                'max_user_id' => $this->customerMaxUserId,
                'first_name' => $this->customerFirstName,
                'last_name' => $this->customerLastName,
                'username' => $this->customerUsername,
            ],
            'delivery_address' => $this->deliveryAddress,
            'items_total' => $this->itemsTotal,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'items_snapshot' => $this->itemsSnapshot,
            'address_review_status' => $this->addressReviewStatus,
            'composition_review_status' => $this->compositionReviewStatus,
            'payment_review_status' => $this->paymentReviewStatus,
            'address_reviewed_by' => $this->addressReviewedBy,
            'address_reviewed_at' => $this->addressReviewedAt,
            'address_rejection_comment' => $this->addressRejectionComment,
            'composition_reviewed_by' => $this->compositionReviewedBy,
            'composition_reviewed_at' => $this->compositionReviewedAt,
            'composition_rejection_comment' => $this->compositionRejectionComment,
            'payment_reviewed_by' => $this->paymentReviewedBy,
            'payment_reviewed_at' => $this->paymentReviewedAt,
            'payment_rejection_comment' => $this->paymentRejectionComment,
            'created_at' => $this->createdAt,
        ];
    }
}
