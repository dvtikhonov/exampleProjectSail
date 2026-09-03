<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly CartItemMutationCoordinator $cartItemMutationCoordinator,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * Возвращает черновик корзины пользователя или null.
     */
    public function getDraftCart(MaxUserIdentity $maxUser): ?CartDto
    {
        $cart = $this->findDraftCart($maxUser);

        if ($cart === null) {
            return null;
        }

        return $this->cartDtoFactory->fromRecord($cart, $maxUser->maxUserId);
    }

    /**
     * Добавляет блюдо в корзину или увеличивает количество.
     *
     * @throws FoodDomainException
     */
    public function addItem(
        MaxUserIdentity $maxUser,
        int $dishId,
        int $quantity,
        ?string $comboRef = null,
        ?int $comboPartnerDishId = null,
    ): CartDto {
        return $this->transactionManager->run(function () use (
            $maxUser,
            $dishId,
            $quantity,
            $comboRef,
            $comboPartnerDishId,
        ): CartDto {
            $cart = $this->cartItemMutationCoordinator->performAddItem(
                CartAddItemPolicy::userCart(),
                $this->findDraftCart($maxUser),
                $dishId,
                $quantity,
                $comboRef,
                $comboPartnerDishId,
                $maxUser->maxUserId,
                null,
            );

            return $this->cartDtoFactory->fromRecord($cart, $maxUser->maxUserId);
        });
    }

    /**
     * Обновляет количество позиции корзины.
     *
     * @throws FoodDomainException
     */
    public function updateItemQuantity(MaxUserIdentity $maxUser, int $cartItemId, int $quantity): CartDto
    {
        return $this->transactionManager->run(function () use ($maxUser, $cartItemId, $quantity): CartDto {
            $cartItem = $this->findOwnedCartItem($maxUser, $cartItemId);

            $this->cartRepository->updateItemQuantity($cartItem->id, $quantity);

            return $this->cartDtoFactory->fromRecord(
                $this->cartRepository->refreshForDto($cartItem->cartId),
                $maxUser->maxUserId,
            );
        });
    }

    /**
     * Удаляет позицию из корзины; при пустой корзине удаляет её целиком.
     *
     * @throws FoodDomainException
     */
    public function removeItem(MaxUserIdentity $maxUser, int $cartItemId): ?CartDto
    {
        return $this->transactionManager->run(function () use ($maxUser, $cartItemId): ?CartDto {
            $cartItem = $this->findOwnedCartItem($maxUser, $cartItemId);
            $cartId = $cartItem->cartId;
            $this->cartRepository->deleteItem($cartItem->id);

            $cart = $this->cartRepository->refreshForDto($cartId);

            if ($cart->isEmpty()) {
                $this->cartRepository->delete($cart->id);

                return null;
            }

            return $this->cartDtoFactory->fromRecord($cart, $maxUser->maxUserId);
        });
    }

    /**
     * Удаляет черновик корзины пользователя.
     */
    public function clear(MaxUserIdentity $maxUser): void
    {
        $this->transactionManager->run(function () use ($maxUser): void {
            $cart = $this->findDraftCart($maxUser);

            if ($cart === null) {
                return;
            }

            $this->cartRepository->delete($cart->id);
        });
    }

    /**
     * Находит черновик корзины пользователя.
     */
    private function findDraftCart(MaxUserIdentity $maxUser): ?CartRecord
    {
        return $this->cartRepository->findDraftByMaxUserId($maxUser->maxUserId);
    }

    /**
     * Находит позицию черновика корзины, принадлежащую пользователю.
     */
    private function findOwnedCartItem(MaxUserIdentity $maxUser, int $cartItemId): CartItemRecord
    {
        $cartItem = $this->cartRepository->findItemById($cartItemId);

        if ($cartItem === null) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        if ($cartItem->cartMaxUserId !== $maxUser->maxUserId) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        if ($cartItem->cartStatus !== CartStatus::Draft) {
            throw new FoodDomainException('Корзина больше недоступна для редактирования.');
        }

        return $cartItem;
    }
}
