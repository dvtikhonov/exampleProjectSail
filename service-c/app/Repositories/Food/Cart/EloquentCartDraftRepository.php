<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Cart;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация draft-порта репозитория корзины.
 */
class EloquentCartDraftRepository implements CartDraftRepositoryInterface
{
    public function __construct(
        private readonly CartMapper $cartMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findDraftByMaxUserId(int $maxUserId): ?CartRecord
    {
        $cart = $this->draftQuery($maxUserId)
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findDraftForUpdate(int $maxUserId): ?CartRecord
    {
        $cart = $this->draftQuery($maxUserId)
            ->with(['restaurant', 'items.dish'])
            ->lockForUpdate()
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        $cart = $this->manualDraftQuery($customerMaxUserId, $managerMaxUserId)
            ->with(['restaurant', 'items.dish', 'items.comboPartnerDish'])
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord
    {
        $cart = $this->manualDraftQuery($customerMaxUserId, $managerMaxUserId)
            ->with(['restaurant', 'items.dish'])
            ->lockForUpdate()
            ->first();

        return $cart !== null ? $this->cartMapper->toRecord($cart) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function createDraft(CartCreateCommand $command): CartRecord
    {
        $cart = Cart::query()->create($this->cartMapper->toCreateAttributes($command));

        return $this->cartMapper->toRecord($cart);
    }

    /**
     * @return Builder<Cart>
     */
    private function draftQuery(int $maxUserId): Builder
    {
        return Cart::query()
            ->where('max_user_id', $maxUserId)
            ->where('status', CartStatus::Draft)
            ->whereNull('created_by_max_user_id');
    }

    /**
     * @return Builder<Cart>
     */
    private function manualDraftQuery(int $customerMaxUserId, int $managerMaxUserId): Builder
    {
        return Cart::query()
            ->where('max_user_id', $customerMaxUserId)
            ->where('created_by_max_user_id', $managerMaxUserId)
            ->where('status', CartStatus::Draft);
    }
}
