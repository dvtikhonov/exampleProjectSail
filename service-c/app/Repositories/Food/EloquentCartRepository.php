<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\Enums\Food\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;

/**
 * Eloquent-реализация репозитория корзины.
 */
class EloquentCartRepository implements CartRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findDraftByMaxUserId(int $maxUserId): ?Cart
    {
        return Cart::query()
            ->where('max_user_id', $maxUserId)
            ->where('status', CartStatus::Draft)
            ->with(['restaurant', 'items.dish'])
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function createDraft(array $attributes): Cart
    {
        return Cart::query()->create($attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshForDto(Cart $cart): Cart
    {
        return $cart->fresh(['restaurant', 'items.dish']);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Cart $cart): void
    {
        $cart->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function findItemById(int $cartItemId): ?CartItem
    {
        return CartItem::query()
            ->with(['cart.restaurant', 'cart.items.dish', 'dish'])
            ->find($cartItemId);
    }

    /**
     * {@inheritDoc}
     */
    public function findItemByCartAndDish(int $cartId, int $dishId): ?CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cartId)
            ->where('dish_id', $dishId)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function createItem(array $attributes): CartItem
    {
        return CartItem::query()->create($attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function incrementItemQuantity(CartItem $cartItem, int $quantity): void
    {
        $cartItem->increment('quantity', $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity(CartItem $cartItem, int $quantity): void
    {
        $cartItem->update(['quantity' => $quantity]);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteItem(CartItem $cartItem): void
    {
        $cartItem->delete();
    }
}
