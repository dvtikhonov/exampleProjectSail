<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Composition\ComboPairValidator;

/**
 * Общая логика добавления позиции в черновик корзины.
 */
class CartItemMutationCoordinator
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly ComboPairValidator $comboPairValidator,
        private readonly CartItemUpserter $cartItemUpserter,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
    ) {}

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
            $cart = $this->cartRepository->createDraft(new CartCreateCommand(
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

        return $this->cartRepository->refreshForDto($cart->id);
    }
}
