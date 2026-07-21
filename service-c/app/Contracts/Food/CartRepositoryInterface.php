<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Cart;
use App\Models\CartItem;

/**
 * Репозиторий корзины пользователя MAX mini-app.
 */
interface CartRepositoryInterface
{
    /**
     * Личный черновик корзины клиента (created_by_max_user_id IS NULL).
     */
    public function findDraftByMaxUserId(int $maxUserId): ?Cart;

    /**
     * Личный черновик корзины с блокировкой строки для обновления (SELECT … FOR UPDATE).
     */
    public function findDraftForUpdate(int $maxUserId): ?Cart;

    /**
     * Ручной черновик корзины клиента, созданный менеджером.
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?Cart;

    /**
     * Ручной черновик корзины с блокировкой строки для обновления.
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?Cart;

    /**
     * Создаёт черновик корзины.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(array $attributes): Cart;

    /**
     * Обновляет адрес доставки корзины.
     */
    public function updateDeliveryAddress(Cart $cart, string $deliveryAddress): void;

    /**
     * Помечает корзину как оформленную.
     */
    public function markAsSubmitted(Cart $cart): void;

    /**
     * Перезагружает корзину со связями для сборки DTO.
     */
    public function refreshForDto(Cart $cart): Cart;

    /**
     * Удаляет корзину.
     */
    public function delete(Cart $cart): void;

    /**
     * Позиция корзины с корзиной, рестораном и блюдом.
     */
    public function findItemById(int $cartItemId): ?CartItem;

    /**
     * Обычная позиция (без комбо) с указанным блюдом.
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItem;

    /**
     * Позиция комбо с указанным блюдом и combo_ref.
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItem;

    /**
     * Создаёт позицию корзины.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): CartItem;

    /**
     * Увеличивает количество позиции корзины.
     */
    public function incrementItemQuantity(CartItem $cartItem, int $quantity): void;

    /**
     * Устанавливает количество позиции корзины.
     */
    public function updateItemQuantity(CartItem $cartItem, int $quantity): void;

    /**
     * Удаляет позицию корзины.
     */
    public function deleteItem(CartItem $cartItem): void;
}
