<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

/**
 * Политика добавления позиции в корзину: user cart vs ручной заказ менеджера.
 */
readonly class CartAddItemPolicy
{
    public function __construct(
        public bool $requireDishAvailable,
        public bool $requirePartnerAvailable,
    ) {}

    /**
     * Политика для корзины пользователя MAX mini-app.
     */
    public static function userCart(): self
    {
        return new self(
            requireDishAvailable: true,
            requirePartnerAvailable: true,
        );
    }

    /**
     * Политика для ручной корзины менеджера от имени клиента.
     */
    public static function manualOrderCart(): self
    {
        return new self(
            requireDishAvailable: false,
            requirePartnerAvailable: false,
        );
    }
}
