<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;
use App\Models\Food\CartItem;

/**
 * Eloquent-реализация item-порта репозитория корзины.
 */
class EloquentCartItemRepository implements CartItemRepositoryInterface
{
    public function __construct(
        private readonly CartMapper $cartMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findItemById(int $cartItemId): ?CartItemRecord
    {
        $item = CartItem::query()
            ->with(['cart.restaurant', 'cart.items.dish', 'dish'])
            ->find($cartItemId);

        return $item !== null ? $this->cartMapper->toItemRecord($item) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItemRecord
    {
        $item = CartItem::query()
            ->where('cart_id', $cartId)
            ->where('dish_id', $dishId)
            ->whereNull('combo_ref')
            ->first();

        return $item !== null ? $this->cartMapper->toItemRecord($item) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItemRecord
    {
        $item = CartItem::query()
            ->where('cart_id', $cartId)
            ->where('dish_id', $dishId)
            ->where('combo_ref', $comboRef)
            ->first();

        return $item !== null ? $this->cartMapper->toItemRecord($item) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function createItem(CartItemCreateCommand $command): CartItemRecord
    {
        $item = CartItem::query()->create($this->cartMapper->toItemCreateAttributes($command));

        return $this->cartMapper->toItemRecord($item);
    }

    /**
     * {@inheritDoc}
     */
    public function incrementItemQuantity(int $cartItemId, int $quantity): void
    {
        CartItem::query()->whereKey($cartItemId)->increment('quantity', $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): void
    {
        CartItem::query()->whereKey($cartItemId)->update(['quantity' => $quantity]);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteItem(int $cartItemId): void
    {
        CartItem::query()->whereKey($cartItemId)->delete();
    }
}
