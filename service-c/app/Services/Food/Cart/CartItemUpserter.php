<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartRecord;

/**
 * Создание или увеличение количества позиций корзины (обычных и комбо).
 */
class CartItemUpserter
{
    public function __construct(
        private readonly CartItemRepositoryInterface $cartRepository,
    ) {}

    /**
     * Создаёт или увеличивает обычную позицию корзины.
     */
    public function upsertRegular(CartRecord $cart, int $dishId, int $quantity): void
    {
        $cartItem = $this->cartRepository->findRegularItemByCartAndDish($cart->id, $dishId);

        if ($cartItem === null) {
            $this->cartRepository->createItem(new CartItemCreateCommand(
                cartId: $cart->id,
                dishId: $dishId,
                quantity: $quantity,
            ));

            return;
        }

        $this->cartRepository->incrementItemQuantity($cartItem->id, $quantity);
    }

    /**
     * Создаёт или увеличивает комбо-позицию корзины.
     */
    public function upsertCombo(
        CartRecord $cart,
        int $dishId,
        int $quantity,
        string $comboRef,
        int $comboPartnerDishId,
    ): void {
        $cartItem = $this->cartRepository->findComboItemByCartDishAndRef($cart->id, $dishId, $comboRef);

        if ($cartItem === null) {
            $this->cartRepository->createItem(new CartItemCreateCommand(
                cartId: $cart->id,
                dishId: $dishId,
                quantity: $quantity,
                comboRef: $comboRef,
                comboPartnerDishId: $comboPartnerDishId,
            ));

            return;
        }

        $this->cartRepository->incrementItemQuantity($cartItem->id, $quantity);
    }
}
