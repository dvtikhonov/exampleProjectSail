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
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(array $attributes): Cart;

    public function refreshForDto(Cart $cart): Cart;

    public function delete(Cart $cart): void;

    /**
     * Позиция корзины с корзиной, рестораном и блюдом.
     */
    public function findItemById(int $cartItemId): ?CartItem;

    public function findItemByCartAndDish(int $cartId, int $dishId): ?CartItem;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): CartItem;

    public function incrementItemQuantity(CartItem $cartItem, int $quantity): void;

    public function updateItemQuantity(CartItem $cartItem, int $quantity): void;

    public function deleteItem(CartItem $cartItem): void;
}
