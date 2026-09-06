<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;

/**
 * Позиции корзины (создание, поиск, изменение количества, удаление).
 */
interface CartItemRepositoryInterface
{
    /**
     * Позиция корзины с корзиной, рестораном и блюдом.
     */
    public function findItemById(int $cartItemId): ?CartItemRecord;

    /**
     * Обычная позиция (без комбо) с указанным блюдом.
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItemRecord;

    /**
     * Позиция комбо с указанным блюдом и combo_ref.
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItemRecord;

    /**
     * Создаёт позицию корзины.
     */
    public function createItem(CartItemCreateCommand $command): CartItemRecord;

    /**
     * Увеличивает количество позиции корзины.
     */
    public function incrementItemQuantity(int $cartItemId, int $quantity): void;

    /**
     * Устанавливает количество позиции корзины.
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): void;

    /**
     * Удаляет позицию корзины.
     */
    public function deleteItem(int $cartItemId): void;
}
