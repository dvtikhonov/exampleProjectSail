<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Cart;
use App\Models\CartItem;

/**
 * Репозиторий корзины пользователя MAX mini-app.
 */
interface CartRepositoryInterface
{
    /**
     * Черновик корзины пользователя с рестораном и позициями.
     */
    public function findDraftByMaxUserId(int $maxUserId): ?Cart;

    /**
     * Черновик корзины с блокировкой строки для обновления (SELECT … FOR UPDATE).
     */
    public function findDraftForUpdate(int $maxUserId): ?Cart;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(array $attributes): Cart;

    public function updateDeliveryAddress(Cart $cart, string $deliveryAddress): void;

    public function markAsSubmitted(Cart $cart): void;

    public function refreshForDto(Cart $cart): Cart;

    public function delete(Cart $cart): void;

    /**
     * Позиция корзины с корзиной, рестораном и блюдом.
     */
    public function findItemById(int $cartItemId): ?CartItem;

    /**
     * Обычная позиция (без комбо) с указанным блюдом.
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItem;

    /**
     * Позиция комбо с указанным блюдом и combo_ref.
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItem;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): CartItem;

    public function incrementItemQuantity(CartItem $cartItem, int $quantity): void;

    public function updateItemQuantity(CartItem $cartItem, int $quantity): void;

    public function deleteItem(CartItem $cartItem): void;
}
