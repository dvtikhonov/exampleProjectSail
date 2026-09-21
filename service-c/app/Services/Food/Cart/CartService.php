<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartDtoFactoryInterface;
use App\Contracts\Food\Cart\CartItemMutationCoordinatorInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartAddItemPolicy;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Cart\CartDraftContext;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Управление корзиной пользователя MAX mini-app.
 */
class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly CartDtoFactoryInterface $cartDtoFactory,
        private readonly CartItemMutationCoordinatorInterface $cartItemMutationCoordinator,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * Возвращает черновик корзины пользователя или null.
     */
    public function getDraftCart(MaxUserIdentity $maxUser): ?CartDto
    {
        $cart = $this->cartItemMutationCoordinator->resolveDraft(CartDraftContext::user($maxUser));

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
            $context = CartDraftContext::user($maxUser);
            $cart = $this->cartItemMutationCoordinator->performAddItem(
                CartAddItemPolicy::userCart(),
                $this->cartItemMutationCoordinator->resolveDraft($context),
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
            $cart = $this->cartItemMutationCoordinator->performUpdateQuantity(
                CartDraftContext::user($maxUser),
                $cartItemId,
                $quantity,
            );

            return $this->cartDtoFactory->fromRecord($cart, $maxUser->maxUserId);
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
            $cart = $this->cartItemMutationCoordinator->performRemoveItem(
                CartDraftContext::user($maxUser),
                $cartItemId,
            );

            if ($cart === null) {
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
            $this->cartItemMutationCoordinator->performClear(CartDraftContext::user($maxUser));
        });
    }
}
