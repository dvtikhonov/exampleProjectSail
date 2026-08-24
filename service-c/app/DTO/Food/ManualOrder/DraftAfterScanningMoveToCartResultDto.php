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
    ) {}

    /**
     * Преобразует результат переноса в массив для JSON-ответа API.
     *
     * @return array{
     *     cart: array<string, mixed>,
     *     delivery_address: string|null,
     *     customer: array{max_user_id: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'cart' => $this->cart->toArray(),
            'delivery_address' => $this->deliveryAddress,
            'customer' => [
                'max_user_id' => $this->customerMaxUserId,
            ],
        ];
    }
}
