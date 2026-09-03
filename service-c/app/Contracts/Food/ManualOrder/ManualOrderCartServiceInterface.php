<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Управление ручной корзиной менеджера от имени клиента.
 */
interface ManualOrderCartServiceInterface
{
    /**
     * Возвращает ручной черновик корзины клиента или null.
     */
    public function getDraftCart(MaxUserIdentity $customer, MaxUserIdentity $manager): ?CartDto;

    /**
     * Обновляет адрес доставки в профиле клиента и ручной корзине (если есть).
     */
    public function updateDeliveryAddress(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        string $deliveryAddress,
    ): ?CartDto;

    /**
     * Добавляет блюдо в ручную корзину или увеличивает количество.
     *
     * @throws FoodDomainException
     */
    public function addItem(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
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
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $cartItemId,
        int $quantity,
    ): CartDto;

    /**
     * Удаляет позицию из ручной корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $cartItemId,
    ): ?CartDto;

    /**
     * Удаляет ручной черновик корзины клиента.
     */
    public function clear(MaxUserIdentity $customer, MaxUserIdentity $manager): void;
}
