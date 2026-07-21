<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\Contracts\Food\DishCatalogRepositoryInterface;
use App\Contracts\Food\ManualOrderCartServiceInterface;
use App\DTO\Food\CartDto;
use App\Enums\Food\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Support\Facades\DB;

/**
 * Управление ручной корзиной менеджера от имени клиента.
 */
class ManualOrderCartService implements ManualOrderCartServiceInterface
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly ComboPairValidator $comboPairValidator,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DishCatalogRepositoryInterface $dishRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDraftCart(MaxUser $customer, MaxUser $manager): ?CartDto
    {
        $cart = $this->findManualDraft($customer, $manager);

        if ($cart === null) {
            return null;
        }

        return $this->cartDtoFactory->fromModel($cart, $customer);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(MaxUser $customer, MaxUser $manager, string $deliveryAddress): ?CartDto
    {
        $this->maxUserDeliveryAddressService->persist($customer, $deliveryAddress);

        $cart = $this->findManualDraft($customer, $manager);

        if ($cart === null) {
            return null;
        }

        $this->cartRepository->updateDeliveryAddress($cart, $deliveryAddress);

        return $this->cartDtoFactory->fromModel(
            $this->cartRepository->refreshForDto($cart),
            $customer,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function addItem(
        MaxUser $customer,
        MaxUser $manager,
        int $dishId,
        int $quantity,
        ?string $comboRef = null,
        ?int $comboPartnerDishId = null,
    ): CartDto {
        return DB::transaction(function () use (
            $customer,
            $manager,
            $dishId,
            $quantity,
            $comboRef,
            $comboPartnerDishId,
        ): CartDto {
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

            $cart = $this->findManualDraft($customer, $manager);

            if ($cart === null) {
                $cart = $this->cartRepository->createDraft([
                    'max_user_id' => $customer->max_user_id,
                    'created_by_max_user_id' => $manager->max_user_id,
                    'restaurant_id' => $restaurant->id,
                    'status' => CartStatus::Draft,
                    'delivery_address' => $this->maxUserDeliveryAddressService->defaultFor($customer),
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
                $customer,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity(
        MaxUser $customer,
        MaxUser $manager,
        int $cartItemId,
        int $quantity,
    ): CartDto {
        return DB::transaction(function () use ($customer, $manager, $cartItemId, $quantity): CartDto {
            $cartItem = $this->findOwnedManualCartItem($customer, $manager, $cartItemId);

            $this->cartRepository->updateItemQuantity($cartItem, $quantity);

            return $this->cartDtoFactory->fromModel(
                $this->cartRepository->refreshForDto($cartItem->cart),
                $customer,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function removeItem(MaxUser $customer, MaxUser $manager, int $cartItemId): ?CartDto
    {
        return DB::transaction(function () use ($customer, $manager, $cartItemId): ?CartDto {
            $cartItem = $this->findOwnedManualCartItem($customer, $manager, $cartItemId);
            $cart = $cartItem->cart;
            $this->cartRepository->deleteItem($cartItem);

            $cart = $this->cartRepository->refreshForDto($cart);

            if ($cart->items->isEmpty()) {
                $this->cartRepository->delete($cart);

                return null;
            }

            return $this->cartDtoFactory->fromModel($cart, $customer);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function clear(MaxUser $customer, MaxUser $manager): void
    {
        DB::transaction(function () use ($customer, $manager): void {
            $cart = $this->findManualDraft($customer, $manager);

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
     * Находит ручной черновик корзины клиента, созданный менеджером.
     */
    private function findManualDraft(MaxUser $customer, MaxUser $manager): ?Cart
    {
        return $this->cartRepository->findManualDraft(
            $customer->max_user_id,
            $manager->max_user_id,
        );
    }

    /**
     * Находит позицию ручного черновика корзины менеджера для клиента.
     */
    private function findOwnedManualCartItem(MaxUser $customer, MaxUser $manager, int $cartItemId): CartItem
    {
        $cartItem = $this->cartRepository->findItemById($cartItemId);

        if ($cartItem === null) {
            throw new FoodDomainException('Cart item not found.', 404);
        }

        $cart = $cartItem->cart;

        if (
            $cart->max_user_id !== $customer->max_user_id
            || $cart->created_by_max_user_id !== $manager->max_user_id
        ) {
            throw new FoodDomainException('Cart item not found.', 404);
        }

        if ($cart->status !== CartStatus::Draft) {
            throw new FoodDomainException('Cart is no longer editable.');
        }

        return $cartItem;
    }
}
