<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Services\Food\Cart\CartAddItemPolicy;
use App\Services\Food\Cart\CartDraftContext;
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
        private readonly CartLifecycleRepositoryInterface $cartLifecycleRepository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDraftCart(MaxUserIdentity $customer, MaxUserIdentity $manager): ?CartDto
    {
        $cart = $this->cartItemMutationCoordinator->resolveDraft(
            CartDraftContext::manual($customer, $manager),
        );

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

        $cart = $this->cartItemMutationCoordinator->resolveDraft(
            CartDraftContext::manual($customer, $manager),
        );

        if ($cart === null) {
            return null;
        }

        $this->cartLifecycleRepository->updateDeliveryAddress($cart->id, $deliveryAddress);

        return $this->cartDtoFactory->fromRecord(
            $this->cartLifecycleRepository->refreshForDto($cart->id),
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
            $context = CartDraftContext::manual($customer, $manager);
            $cart = $this->cartItemMutationCoordinator->performAddItem(
                CartAddItemPolicy::manualOrderCart(),
                $this->cartItemMutationCoordinator->resolveDraft($context),
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
            $cart = $this->cartItemMutationCoordinator->performUpdateQuantity(
                CartDraftContext::manual($customer, $manager),
                $cartItemId,
                $quantity,
            );

            return $this->cartDtoFactory->fromRecord($cart, $customer->maxUserId);
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
            $cart = $this->cartItemMutationCoordinator->performRemoveItem(
                CartDraftContext::manual($customer, $manager),
                $cartItemId,
            );

            if ($cart === null) {
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
            $this->cartItemMutationCoordinator->performClear(
                CartDraftContext::manual($customer, $manager),
            );
        });
    }
}
