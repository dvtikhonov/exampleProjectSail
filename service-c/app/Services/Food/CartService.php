<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\Contracts\Food\CartServiceInterface;
use App\Contracts\Food\DishCatalogRepositoryInterface;
use App\DTO\Food\CartDto;
use App\Enums\Food\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Support\Facades\DB;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly ComboPairValidator $comboPairValidator,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DishCatalogRepositoryInterface $dishRepository,
    ) {}

    /**
     * Возвращает черновик корзины пользователя или null.
     */
    public function getDraftCart(MaxUser $maxUser): ?CartDto
    {
        $cart = $this->findDraftCart($maxUser);

        if ($cart === null) {
            return null;
        }

        return $this->cartDtoFactory->fromModel($cart, $maxUser);
    }

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
    ): CartDto {
        return DB::transaction(function () use ($maxUser, $dishId, $quantity, $comboRef, $comboPartnerDishId): CartDto {
            $dish = $this->dishRepository->findAvailableWithRestaurant($dishId);

            if ($dish === null) {
                throw new FoodDomainException('Dish not found.', 404);
            }

            if (! $dish->is_available) {
                throw new FoodDomainException('Dish is not available.');
            }

            $restaurant = $dish->menuCategory->restaurant;

            if (! $restaurant->is_active) {
                throw new FoodDomainException('Restaurant is not available.');
            }

            $cart = $this->findDraftCart($maxUser);

            if ($cart === null) {
                $cart = $this->cartRepository->createDraft([
                    'max_user_id' => $maxUser->max_user_id,
                    'restaurant_id' => $restaurant->id,
                    'status' => CartStatus::Draft,
                    'delivery_address' => $this->maxUserDeliveryAddressService->defaultFor($maxUser),
                ]);
            } elseif ($cart->restaurant_id !== $restaurant->id) {
                throw new FoodDomainException(
                    'Cart already contains items from another restaurant. Clear the cart before adding dishes from a different restaurant.',
                );
            }

            if ($comboRef !== null && $comboPartnerDishId !== null) {
                $this->comboPairValidator->validatePair($dish, $comboPartnerDishId);
                $this->upsertComboCartItem($cart, $dish->id, $quantity, $comboRef, $comboPartnerDishId);
            } else {
                $this->upsertRegularCartItem($cart, $dish->id, $quantity);
            }

            return $this->cartDtoFactory->fromModel(
                $this->cartRepository->refreshForDto($cart),
                $maxUser,
            );
        });
    }

    /**
     * Обновляет количество позиции корзины.
     *
     * @throws FoodDomainException
     */
    public function updateItemQuantity(MaxUser $maxUser, int $cartItemId, int $quantity): CartDto
    {
        return DB::transaction(function () use ($maxUser, $cartItemId, $quantity): CartDto {
            $cartItem = $this->findOwnedCartItem($maxUser, $cartItemId);

            $this->cartRepository->updateItemQuantity($cartItem, $quantity);

            return $this->cartDtoFactory->fromModel(
                $this->cartRepository->refreshForDto($cartItem->cart),
                $maxUser,
            );
        });
    }

    /**
     * Удаляет позицию из корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(MaxUser $maxUser, int $cartItemId): ?CartDto
    {
        return DB::transaction(function () use ($maxUser, $cartItemId): ?CartDto {
            $cartItem = $this->findOwnedCartItem($maxUser, $cartItemId);
            $cart = $cartItem->cart;
            $this->cartRepository->deleteItem($cartItem);

            $cart = $this->cartRepository->refreshForDto($cart);

            if ($cart->items->isEmpty()) {
                $this->cartRepository->delete($cart);

                return null;
            }

            return $this->cartDtoFactory->fromModel($cart, $maxUser);
        });
    }

    /**
     * Удаляет черновик корзины пользователя.
     */
    public function clear(MaxUser $maxUser): void
    {
        DB::transaction(function () use ($maxUser): void {
            $cart = $this->findDraftCart($maxUser);

            if ($cart === null) {
                return;
            }

            $this->cartRepository->delete($cart);
        });
    }

    /**
     * Создаёт или увеличивает обычную позицию корзины.
     */
    private function upsertRegularCartItem(Cart $cart, int $dishId, int $quantity): void
    {
        $cartItem = $this->cartRepository->findRegularItemByCartAndDish($cart->id, $dishId);

        if ($cartItem === null) {
            $this->cartRepository->createItem([
                'cart_id' => $cart->id,
                'dish_id' => $dishId,
                'quantity' => $quantity,
            ]);

            return;
        }

        $this->cartRepository->incrementItemQuantity($cartItem, $quantity);
    }

    /**
     * Создаёт или увеличивает комбо-позицию корзины.
     */
    private function upsertComboCartItem(
        Cart $cart,
        int $dishId,
        int $quantity,
        string $comboRef,
        int $comboPartnerDishId,
    ): void {
        $cartItem = $this->cartRepository->findComboItemByCartDishAndRef($cart->id, $dishId, $comboRef);

        if ($cartItem === null) {
            $this->cartRepository->createItem([
                'cart_id' => $cart->id,
                'dish_id' => $dishId,
                'quantity' => $quantity,
                'combo_ref' => $comboRef,
                'combo_partner_dish_id' => $comboPartnerDishId,
            ]);

            return;
        }

        $this->cartRepository->incrementItemQuantity($cartItem, $quantity);
    }

    /**
     * Находит черновик корзины пользователя.
     */
    private function findDraftCart(MaxUser $maxUser): ?Cart
    {
        return $this->cartRepository->findDraftByMaxUserId($maxUser->max_user_id);
    }

    /**
     * Находит позицию черновика корзины, принадлежащую пользователю.
     */
    private function findOwnedCartItem(MaxUser $maxUser, int $cartItemId): CartItem
    {
        $cartItem = $this->cartRepository->findItemById($cartItemId);

        if ($cartItem === null) {
            throw new FoodDomainException('Cart item not found.', 404);
        }

        if ($cartItem->cart->max_user_id !== $maxUser->max_user_id) {
            throw new FoodDomainException('Cart item not found.', 404);
        }

        if ($cartItem->cart->status !== CartStatus::Draft) {
            throw new FoodDomainException('Cart is no longer editable.');
        }

        return $cartItem;
    }
}
