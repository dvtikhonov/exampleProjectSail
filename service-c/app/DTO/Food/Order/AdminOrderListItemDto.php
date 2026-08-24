<?php

declare(strict_types=1);

namespace App\DTO\Food\Order;

/**
 * Краткие данные заказа для списка администратора.
 */
readonly class AdminOrderListItemDto
{
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
        public ?string $deliveryDate,
        public string $itemsTotal,
        public ?string $deliveryCost,
        public string $total,
        public string $addressReviewStatus,
        public string $compositionReviewStatus,
        public string $paymentReviewStatus,
        public ?string $lastMessageAt,
        public int $unreadCount,
        public string $createdAt,
    ) {}

    /**
     * Преобразует элемент списка админских заказов в массив.
     *
     * @return array{
     *     id: int,
     *     status: string,
     *     restaurant_id: int,
     *     restaurant_name: string,
     *     customer: array{max_user_id: int, first_name: string|null, last_name: string|null, username: string|null},
     *     delivery_address: string|null,
     *     delivery_date: string|null,
     *     items_total: string,
     *     delivery_cost: string|null,
     *     total: string,
     *     address_review_status: string,
     *     composition_review_status: string,
     *     payment_review_status: string,
     *     last_message_at: string|null,
     *     unread_count: int,
     *     created_at: string
     * }
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
            'delivery_date' => $this->deliveryDate,
            'items_total' => $this->itemsTotal,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'address_review_status' => $this->addressReviewStatus,
            'composition_review_status' => $this->compositionReviewStatus,
            'payment_review_status' => $this->paymentReviewStatus,
            'last_message_at' => $this->lastMessageAt,
            'unread_count' => $this->unreadCount,
            'created_at' => $this->createdAt,
        ];
    }
}
