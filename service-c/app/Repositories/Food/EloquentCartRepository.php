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
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findDraftForUpdate(int $maxUserId): ?Cart
    {
        return Cart::query()
            ->where('max_user_id', $maxUserId)
            ->where('status', CartStatus::Draft)
            ->with(['restaurant', 'items.dish'])
            ->lockForUpdate()
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
    public function updateDeliveryAddress(Cart $cart, string $deliveryAddress): void
    {
        $cart->update(['delivery_address' => $deliveryAddress]);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsSubmitted(Cart $cart): void
    {
        $cart->update(['status' => CartStatus::Submitted]);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshForDto(Cart $cart): Cart
    {
        return $cart->fresh(['restaurant', 'items.dish', 'items.comboPartnerDish']);
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
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cartId)
            ->where('dish_id', $dishId)
            ->whereNull('combo_ref')
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cartId)
            ->where('dish_id', $dishId)
            ->where('combo_ref', $comboRef)
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
