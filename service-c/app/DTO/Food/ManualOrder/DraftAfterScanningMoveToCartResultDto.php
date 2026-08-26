<?php

declare(strict_types=1);

namespace App\DTO\Food\ManualOrder;

use App\DTO\Food\Cart\CartDto;

/**
 * Результат переноса заказа «Черновик после сканирования» в ручную корзину.
 */
readonly class DraftAfterScanningMoveToCartResultDto
{
    public function __construct(
        public CartDto $cart,
        public int $customerMaxUserId,
        public ?string $deliveryAddress,
        public ?string $deliveryDate = null,
    ) {}

    /**
     * Преобразует результат переноса в массив для JSON-ответа API.
     *
     * @return array{
     *     cart: array<string, mixed>,
     *     delivery_address: string|null,
     *     delivery_date: string|null,
     *     customer: array{max_user_id: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'cart' => $this->cart->toArray(),
            'delivery_address' => $this->deliveryAddress,
            'delivery_date' => $this->deliveryDate,
            'customer' => [
                'max_user_id' => $this->customerMaxUserId,
            ],
        ];
    }
}
