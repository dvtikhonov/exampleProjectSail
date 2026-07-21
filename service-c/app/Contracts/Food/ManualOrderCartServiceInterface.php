<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\CartDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Управление ручной корзиной менеджера от имени клиента.
 */
interface ManualOrderCartServiceInterface
{
    /**
     * Возвращает ручной черновик корзины клиента или null.
     */
    public function getDraftCart(MaxUser $customer, MaxUser $manager): ?CartDto;

    /**
     * Обновляет адрес доставки в профиле клиента и ручной корзине (если есть).
     */
    public function updateDeliveryAddress(MaxUser $customer, MaxUser $manager, string $deliveryAddress): ?CartDto;

    /**
     * Добавляет блюдо в ручную корзину или увеличивает количество.
     *
     * @throws FoodDomainException
     */
    public function addItem(
        MaxUser $customer,
        MaxUser $manager,
        int $dishId,
        int $quantity,
        ?string $comboRef = null,
        ?int $comboPartnerDishId = null,
    ): CartDto;

    /**
     * Обновляет количество позиции ручной корзины.
     *
     * @throws FoodDomainException
     */
    public function updateItemQuantity(
        MaxUser $customer,
        MaxUser $manager,
        int $cartItemId,
        int $quantity,
    ): CartDto;

    /**
     * Удаляет позицию из ручной корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(MaxUser $customer, MaxUser $manager, int $cartItemId): ?CartDto;

    /**
     * Удаляет ручной черновик корзины клиента.
     */
    public function clear(MaxUser $customer, MaxUser $manager): void;
}
