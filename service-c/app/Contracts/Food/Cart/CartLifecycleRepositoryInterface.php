<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartRecord;

/**
 * Жизненный цикл корзины: адрес, submit, refresh, удаление.
 */
interface CartLifecycleRepositoryInterface
{
    /**
     * Обновляет адрес доставки корзины.
     */
    public function updateDeliveryAddress(int $cartId, string $deliveryAddress): void;

    /**
     * Помечает корзину как оформленную.
     */
    public function markAsSubmitted(int $cartId): void;

    /**
     * Перезагружает корзину со связями для сборки DTO.
     */
    public function refreshForDto(int $cartId): CartRecord;

    /**
     * Удаляет корзину.
     */
    public function delete(int $cartId): void;
}
