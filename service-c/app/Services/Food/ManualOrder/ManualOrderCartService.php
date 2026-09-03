<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Cart\CartAddItemPolicy;
use App\Services\Food\Cart\CartDtoFactory;
use App\Services\Food\Cart\CartItemMutationCoordinator;

/**
 * Управление ручной корзиной менеджера от имени клиента.
 */
class ManualOrderCartService implements ManualOrderCartServiceInterface
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly CartItemMutationCoordinator $cartItemMutationCoordinator,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDraftCart(MaxUserIdentity $customer, MaxUserIdentity $manager): ?CartDto
    {
        $cart = $this->findManualDraft($customer, $manager);

        if ($cart === null) {
            return null;
        }

        return $this->cartDtoFactory->fromRecord($cart, $customer->maxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        string $deliveryAddress,
    ): ?CartDto {
        $this->maxUserDeliveryAddressService->persistForMaxUserId($customer->maxUserId, $deliveryAddress);

        $cart = $this->findManualDraft($customer, $manager);

        if ($cart === null) {
            return null;
        }

        $this->cartRepository->updateDeliveryAddress($cart->id, $deliveryAddress);

        return $this->cartDtoFactory->fromRecord(
            $this->cartRepository->refreshForDto($cart->id),
            $customer->maxUserId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function addItem(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $dishId,
        int $quantity,
        ?string $comboRef = null,
        ?int $comboPartnerDishId = null,
    ): CartDto {
        return $this->transactionManager->run(function () use (
            $customer,
            $manager,
            $dishId,
            $quantity,
            $comboRef,
            $comboPartnerDishId,
        ): CartDto {
            $cart = $this->cartItemMutationCoordinator->performAddItem(
                CartAddItemPolicy::manualOrderCart(),
                $this->findManualDraft($customer, $manager),
                $dishId,
                $quantity,
                $comboRef,
                $comboPartnerDishId,
                $customer->maxUserId,
                $manager->maxUserId,
            );

            return $this->cartDtoFactory->fromRecord($cart, $customer->maxUserId);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $cartItemId,
        int $quantity,
    ): CartDto {
        return $this->transactionManager->run(function () use ($customer, $manager, $cartItemId, $quantity): CartDto {
            $cartItem = $this->findOwnedManualCartItem($customer, $manager, $cartItemId);

            $this->cartRepository->updateItemQuantity($cartItem->id, $quantity);

            return $this->cartDtoFactory->fromRecord(
                $this->cartRepository->refreshForDto($cartItem->cartId),
                $customer->maxUserId,
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function removeItem(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $cartItemId,
    ): ?CartDto {
        return $this->transactionManager->run(function () use ($customer, $manager, $cartItemId): ?CartDto {
            $cartItem = $this->findOwnedManualCartItem($customer, $manager, $cartItemId);
            $cartId = $cartItem->cartId;
            $this->cartRepository->deleteItem($cartItem->id);

            $cart = $this->cartRepository->refreshForDto($cartId);

            if ($cart->isEmpty()) {
                $this->cartRepository->delete($cart->id);

                return null;
            }

            return $this->cartDtoFactory->fromRecord($cart, $customer->maxUserId);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function clear(MaxUserIdentity $customer, MaxUserIdentity $manager): void
    {
        $this->transactionManager->run(function () use ($customer, $manager): void {
            $cart = $this->findManualDraft($customer, $manager);

            if ($cart === null) {
                return;
            }

            $this->cartRepository->delete($cart->id);
        });
    }

    /**
     * Находит ручной черновик корзины клиента, созданный менеджером.
     */
    private function findManualDraft(MaxUserIdentity $customer, MaxUserIdentity $manager): ?CartRecord
    {
        return $this->cartRepository->findManualDraft(
            $customer->maxUserId,
            $manager->maxUserId,
        );
    }

    /**
     * Находит позицию ручного черновика корзины менеджера для клиента.
     */
    private function findOwnedManualCartItem(
        MaxUserIdentity $customer,
        MaxUserIdentity $manager,
        int $cartItemId,
    ): CartItemRecord {
        $cartItem = $this->cartRepository->findItemById($cartItemId);

        if ($cartItem === null) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        if (
            $cartItem->cartMaxUserId !== $customer->maxUserId
            || $cartItem->cartCreatedByMaxUserId !== $manager->maxUserId
        ) {
            throw new FoodDomainException('Позиция корзины не найдена.', 404);
        }

        if ($cartItem->cartStatus !== CartStatus::Draft) {
            throw new FoodDomainException('Корзина больше недоступна для редактирования.');
        }

        return $cartItem;
    }
}
