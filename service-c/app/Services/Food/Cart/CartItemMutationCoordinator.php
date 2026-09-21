<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartItemMutationCoordinatorInterface;
use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Composition\ComboPairValidatorInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Cart\CartAddItemPolicy;
use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartDraftContext;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Общая логика мутаций позиций черновика корзины (user и manual).
 */
class CartItemMutationCoordinator implements CartItemMutationCoordinatorInterface
{
    public function __construct(
        private readonly CartDraftRepositoryInterface $cartDraftRepository,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly CartLifecycleRepositoryInterface $cartLifecycleRepository,
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly ComboPairValidatorInterface $comboPairValidator,
        private readonly CartItemUpserter $cartItemUpserter,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
    ) {}

    /**
     * Находит черновик корзины по контексту (личная или ручная).
     */
    public function resolveDraft(CartDraftContext $context): ?CartRecord
    {
        if ($context->createdByMaxUserId === null) {
            return $this->cartDraftRepository->findDraftByMaxUserId($context->ownerMaxUserId);
        }

        return $this->cartDraftRepository->findManualDraft(
            $context->ownerMaxUserId,
            $context->createdByMaxUserId,
        );
    }

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
    ): CartRecord {
        $dish = $this->dishRepository->findAvailableWithRestaurant($dishId);

        if ($dish === null) {
            throw new FoodDomainException('Блюдо не найдено.', 404);
        }

        if ($policy->requireDishAvailable && ! $dish->isAvailable) {
            throw new FoodDomainException('Блюдо недоступно.');
        }

        $restaurant = $dish->menuCategory?->restaurant;

        if ($restaurant === null || ! $restaurant->isActive) {
            throw new FoodDomainException('Ресторан недоступен.');
        }

        if ($cart === null) {
            $cart = $this->cartDraftRepository->createDraft(new CartCreateCommand(
                maxUserId: $cartOwnerMaxUserId,
                createdByMaxUserId: $cartCreatedByMaxUserId,
                restaurantId: $restaurant->id,
                status: CartStatus::Draft,
                deliveryAddress: $this->maxUserDeliveryAddressService->defaultForMaxUserId($cartOwnerMaxUserId),
            ));
        } elseif ($cart->restaurantId !== $restaurant->id) {
            throw new FoodDomainException(
                'В корзине уже есть блюда из другого ресторана. Очистите корзину перед добавлением блюд из другого ресторана.',
            );
        }

        if ($comboRef !== null && $comboPartnerDishId !== null) {
            $this->comboPairValidator->validatePair(
                $dish,
                $comboPartnerDishId,
                requirePartnerAvailable: $policy->requirePartnerAvailable,
            );
            $this->cartItemUpserter->upsertCombo(
                $cart,
                $dish->id,
                $quantity,
                $comboRef,
                $comboPartnerDishId,
            );
        } else {
            $this->cartItemUpserter->upsertRegular($cart, $dish->id, $quantity);
        }

        return $this->cartLifecycleRepository->refreshForDto($cart->id);
    }

    /**
     * Обновляет количество позиции черновика корзины.
     *
     * @throws FoodDomainException
     */
    public function performUpdateQuantity(
        CartDraftContext $context,
        int $cartItemId,
        int $quantity,
    ): CartRecord {
        $cartItem = $this->findOwnedDraftItem($context, $cartItemId);

        $this->cartItemRepository->updateItemQuantity($cartItem->id, $quantity);

        return $this->cartLifecycleRepository->refreshForDto($cartItem->cartId);
    }

    /**
     * Удаляет позицию; при пустой корзине удаляет её и возвращает null.
     *
     * @throws FoodDomainException
     */
    public function performRemoveItem(CartDraftContext $context, int $cartItemId): ?CartRecord
    {
        $cartItem = $this->findOwnedDraftItem($context, $cartItemId);
        $cartId = $cartItem->cartId;

        $this->cartItemRepository->deleteItem($cartItem->id);

        $cart = $this->cartLifecycleRepository->refreshForDto($cartId);

        if ($cart->isEmpty()) {
            $this->cartLifecycleRepository->delete($cart->id);

            return null;
        }

        return $cart;
    }

    /**
     * Удаляет черновик корзины по контексту (no-op, если черновика нет).
     */
    public function performClear(CartDraftContext $context): void
    {
        $cart = $this->resolveDraft($context);

        if ($cart === null) {
            return;
        }

        $this->cartLifecycleRepository->delete($cart->id);
    }

    /**
     * Находит позицию и проверяет ownership черновика из контекста.
     *
     * @throws FoodDomainException
     */
    private function findOwnedDraftItem(CartDraftContext $context, int $cartItemId): CartItemRecord
    {
        $cartItem = $this->cartItemRepository->findItemById($cartItemId);

        if ($cartItem === null) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        return $this->assertOwnedDraftItem($cartItem, $context);
    }

    /**
     * Проверяет, что позиция принадлежит черновику из контекста.
     *
     * @throws FoodDomainException
     */
    private function assertOwnedDraftItem(CartItemRecord $cartItem, CartDraftContext $context): CartItemRecord
    {
        if ($cartItem->cartMaxUserId !== $context->ownerMaxUserId) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        // Строгое сравнение: user-контекст (createdBy === null) отсекает manual-позиции;
        // manual-контекст — чужие manual-корзины другого менеджера.
        if ($cartItem->cartCreatedByMaxUserId !== $context->createdByMaxUserId) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        if ($cartItem->cartStatus !== CartStatus::Draft) {
            throw new FoodDomainException('Корзина больше недоступна для редактирования.');
        }

        return $cartItem;
    }
}
