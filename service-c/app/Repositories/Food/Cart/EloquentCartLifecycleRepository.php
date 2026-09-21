<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Cart;

/**
 * Eloquent-реализация lifecycle-порта репозитория корзины.
 */
class EloquentCartLifecycleRepository implements CartLifecycleRepositoryInterface
{
    public function __construct(
        private readonly CartMapper $cartMapper,
    ) {}

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
}
