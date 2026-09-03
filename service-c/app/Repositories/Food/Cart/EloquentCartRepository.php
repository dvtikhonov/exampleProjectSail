<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Cart;
use App\Models\Food\CartItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация репозитория корзины.
 */
class EloquentCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly CartMapper $cartMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findDraftByMaxUserId(int $maxUserId): ?CartRecord
    {
        $cart = $this->draftQuery($maxUserId)
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findDraftForUpdate(int $maxUserId): ?CartRecord
    {
        $cart = $this->draftQuery($maxUserId)
            ->with(['restaurant', 'items.dish'])
            ->lockForUpdate()
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        $cart = $this->manualDraftQuery($customerMaxUserId, $managerMaxUserId)
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        $cart = $this->manualDraftQuery($customerMaxUserId, $managerMaxUserId)
            ->with(['restaurant', 'items.dish'])
            ->lockForUpdate()
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function createDraft(CartCreateCommand $command): CartRecord
    {
        $cart = Cart::query()->create($this->cartMapper->toCreateAttributes($command));

        return $this->cartMapper->toRecord($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(int $cartId, string $deliveryAddress): void
    {
        Cart::query()->whereKey($cartId)->update(['delivery_address' => $deliveryAddress]);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsSubmitted(int $cartId): void
    {
        Cart::query()->whereKey($cartId)->update(['status' => CartStatus::Submitted]);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshForDto(int $cartId): CartRecord
    {
        $cart = Cart::query()
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->findOrFail($cartId);

        return $this->cartMapper->toRecord($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $cartId): void
    {
        Cart::query()->whereKey($cartId)->delete();
    }

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

    /**
     * @return Builder<Cart>
     */
    private function draftQuery(int $maxUserId): Builder
    {
        return Cart::query()
            ->where('max_user_id', $maxUserId)
            ->where('status', CartStatus::Draft)
            ->whereNull('created_by_max_user_id');
    }

    /**
     * @return Builder<Cart>
     */
    private function manualDraftQuery(int $customerMaxUserId, int $managerMaxUserId): Builder
    {
        return Cart::query()
            ->where('max_user_id', $customerMaxUserId)
            ->where('created_by_max_user_id', $managerMaxUserId)
            ->where('status', CartStatus::Draft);
    }
}
