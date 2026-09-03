<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;

/**
 * Репозиторий корзины пользователя MAX mini-app.
 */
interface CartRepositoryInterface
{
    /**
     * Личный черновик корзины клиента (created_by_max_user_id IS NULL).
     */
    public function findDraftByMaxUserId(int $maxUserId): ?CartRecord;

    /**
     * Личный черновик корзины с блокировкой строки для обновления (SELECT … FOR UPDATE).
     */
    public function findDraftForUpdate(int $maxUserId): ?CartRecord;

    /**
     * Ручной черновик корзины клиента, созданный менеджером.
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord;

    /**
     * Ручной черновик корзины с блокировкой строки для обновления.
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord;

    /**
     * Создаёт черновик корзины.
     */
    public function createDraft(CartCreateCommand $command): CartRecord;

    /**
     * Обновляет адрес доставки корзины.
     */
    public function updateDeliveryAddress(int $cartId, string $deliveryAddress): void;

    /**
     * Помечает корзину как оформленную.
     */
    public function markAsSubmitted(int $cartId): void;

    /**
     * Перезагружает корзину со связями для сборки DTO.
     */
    public function refreshForDto(int $cartId): CartRecord;

    /**
     * Удаляет корзину.
     */
    public function delete(int $cartId): void;

    /**
     * Позиция корзины с корзиной, рестораном и блюдом.
     */
    public function findItemById(int $cartItemId): ?CartItemRecord;

    /**
     * Обычная позиция (без комбо) с указанным блюдом.
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItemRecord;

    /**
     * Позиция комбо с указанным блюдом и combo_ref.
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItemRecord;

    /**
     * Создаёт позицию корзины.
     */
    public function createItem(CartItemCreateCommand $command): CartItemRecord;

    /**
     * Увеличивает количество позиции корзины.
     */
    public function incrementItemQuantity(int $cartItemId, int $quantity): void;

    /**
     * Устанавливает количество позиции корзины.
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): void;

    /**
     * Удаляет позицию корзины.
     */
    public function deleteItem(int $cartItemId): void;
}
