<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartAddItemPolicy;
use App\DTO\Food\Cart\CartDraftContext;
use App\DTO\Food\Cart\CartRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Общая логика мутаций позиций черновика корзины (user и manual).
 */
interface CartItemMutationCoordinatorInterface
{
    /**
     * Находит черновик корзины по контексту (личная или ручная).
     */
    public function resolveDraft(CartDraftContext $context): ?CartRecord;

    /**
     * Добавляет блюдо в существующий или новый черновик корзины.
     *
     * @throws FoodDomainException
     */
    public function performAddItem(
        CartAddItemPolicy $policy,
        ?CartRecord $cart,
        int $dishId,
        int $quantity,
        ?string $comboRef,
        ?int $comboPartnerDishId,
        int $cartOwnerMaxUserId,
        ?int $cartCreatedByMaxUserId,
    ): CartRecord;

    /**
     * Обновляет количество позиции черновика корзины.
     *
     * @throws FoodDomainException
     */
    public function performUpdateQuantity(
        CartDraftContext $context,
        int $cartItemId,
        int $quantity,
    ): CartRecord;

    /**
     * Удаляет позицию; при пустой корзине удаляет её и возвращает null.
     *
     * @throws FoodDomainException
     */
    public function performRemoveItem(CartDraftContext $context, int $cartItemId): ?CartRecord;

    /**
     * Удаляет черновик корзины по контексту (no-op, если черновика нет).
     */
    public function performClear(CartDraftContext $context): void;
}
