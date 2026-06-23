<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\CartDto;
use App\Enums\Food\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dish;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Support\Facades\DB;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
class CartService
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
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
    public function addItem(MaxUser $maxUser, int $dishId, int $quantity): CartDto
    {
        return DB::transaction(function () use ($maxUser, $dishId, $quantity): CartDto {
            $dish = Dish::query()
                ->with('menuCategory.restaurant')
                ->find($dishId);

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
                $cart = Cart::query()->create([
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

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('dish_id', $dish->id)
                ->first();

            if ($cartItem === null) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'dish_id' => $dish->id,
                    'quantity' => $quantity,
                ]);
            } else {
                $cartItem->increment('quantity', $quantity);
            }

            return $this->cartDtoFactory->fromModel(
                $cart->fresh(['restaurant', 'items.dish']),
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

            $cartItem->update(['quantity' => $quantity]);

            return $this->cartDtoFactory->fromModel(
                $cartItem->cart->fresh(['restaurant', 'items.dish']),
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
            $cartItem->delete();

            $cart = $cart->fresh(['restaurant', 'items.dish']);

            if ($cart->items->isEmpty()) {
                $cart->delete();

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

            $cart->delete();
        });
    }

    private function findDraftCart(MaxUser $maxUser): ?Cart
    {
        return Cart::query()
            ->where('max_user_id', $maxUser->max_user_id)
            ->where('status', CartStatus::Draft)
            ->with(['restaurant', 'items.dish'])
            ->first();
    }

    private function findOwnedCartItem(MaxUser $maxUser, int $cartItemId): CartItem
    {
        $cartItem = CartItem::query()
            ->with(['cart.restaurant', 'cart.items.dish', 'dish'])
            ->find($cartItemId);

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
