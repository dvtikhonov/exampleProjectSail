<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
interface CartServiceInterface
{
    /**
     * Возвращает черновик корзины пользователя или null.
     */
    public function getDraftCart(MaxUserIdentity $maxUser): ?CartDto;

    /**
     * Добавляет блюдо в корзину или увеличивает количество.
     *
     * @throws FoodDomainException
     */
    public function addItem(
        MaxUserIdentity $maxUser,
        int $dishId,
        int $quantity,
        ?string $comboRef = null,
        ?int $comboPartnerDishId = null,
    ): CartDto;

    /**
     * Обновляет количество позиции корзины.
     *
     * @throws FoodDomainException
     */
    public function updateItemQuantity(MaxUserIdentity $maxUser, int $cartItemId, int $quantity): CartDto;

    /**
     * Удаляет позицию из корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(MaxUserIdentity $maxUser, int $cartItemId): ?CartDto;

    /**
     * Удаляет черновик корзины пользователя.
     */
    public function clear(MaxUserIdentity $maxUser): void;
}
