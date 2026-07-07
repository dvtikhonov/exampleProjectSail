<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\CartDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
interface CartServiceInterface
{
    /**
     * Возвращает черновик корзины пользователя или null.
     */
    public function getDraftCart(MaxUser $maxUser): ?CartDto;

    /**
     * Добавляет блюдо в корзину или увеличивает количество.
     *
     * @throws FoodDomainException
     */
    public function addItem(
        MaxUser $maxUser,
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
    public function updateItemQuantity(MaxUser $maxUser, int $cartItemId, int $quantity): CartDto;

    /**
     * Удаляет позицию из корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(MaxUser $maxUser, int $cartItemId): ?CartDto;

    /**
     * Удаляет черновик корзины пользователя.
     */
    public function clear(MaxUser $maxUser): void;
}
