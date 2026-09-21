<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;

/**
 * Composition-адаптер полного порта корзины: делегирует в draft / item / lifecycle.
 */
class EloquentCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly CartDraftRepositoryInterface $draftRepository,
        private readonly CartItemRepositoryInterface $itemRepository,
        private readonly CartLifecycleRepositoryInterface $lifecycleRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findDraftByMaxUserId(int $maxUserId): ?CartRecord
    {
        return $this->draftRepository->findDraftByMaxUserId($maxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function findDraftForUpdate(int $maxUserId): ?CartRecord
    {
        return $this->draftRepository->findDraftForUpdate($maxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        return $this->draftRepository->findManualDraft($customerMaxUserId, $managerMaxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        return $this->draftRepository->findManualDraftForUpdate($customerMaxUserId, $managerMaxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function createDraft(CartCreateCommand $command): CartRecord
    {
        return $this->draftRepository->createDraft($command);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(int $cartId, string $deliveryAddress): void
    {
        $this->lifecycleRepository->updateDeliveryAddress($cartId, $deliveryAddress);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsSubmitted(int $cartId): void
    {
        $this->lifecycleRepository->markAsSubmitted($cartId);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshForDto(int $cartId): CartRecord
    {
        return $this->lifecycleRepository->refreshForDto($cartId);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $cartId): void
    {
        $this->lifecycleRepository->delete($cartId);
    }

    /**
     * {@inheritDoc}
     */
    public function findItemById(int $cartItemId): ?CartItemRecord
    {
        return $this->itemRepository->findItemById($cartItemId);
    }

    /**
     * {@inheritDoc}
     */
    public function findRegularItemByCartAndDish(int $cartId, int $dishId): ?CartItemRecord
    {
        return $this->itemRepository->findRegularItemByCartAndDish($cartId, $dishId);
    }

    /**
     * {@inheritDoc}
     */
    public function findComboItemByCartDishAndRef(int $cartId, int $dishId, string $comboRef): ?CartItemRecord
    {
        return $this->itemRepository->findComboItemByCartDishAndRef($cartId, $dishId, $comboRef);
    }

    /**
     * {@inheritDoc}
     */
    public function createItem(CartItemCreateCommand $command): CartItemRecord
    {
        return $this->itemRepository->createItem($command);
    }

    /**
     * {@inheritDoc}
     */
    public function incrementItemQuantity(int $cartItemId, int $quantity): void
    {
        $this->itemRepository->incrementItemQuantity($cartItemId, $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): void
    {
        $this->itemRepository->updateItemQuantity($cartItemId, $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteItem(int $cartItemId): void
    {
        $this->itemRepository->deleteItem($cartItemId);
    }
}
