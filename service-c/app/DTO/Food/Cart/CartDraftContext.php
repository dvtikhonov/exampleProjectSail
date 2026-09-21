<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

use App\DTO\Food\Shared\MaxUserIdentity;

/**
 * Контекст черновика корзины: личная (user) или ручная (manual).
 */
readonly class CartDraftContext
{
    public function __construct(
        public int $ownerMaxUserId,
        public ?int $createdByMaxUserId,
    ) {}

    /**
     * Черновик корзины пользователя MAX mini-app (created_by = null).
     */
    public static function user(MaxUserIdentity $maxUser): self
    {
        return new self(
            ownerMaxUserId: $maxUser->maxUserId,
            createdByMaxUserId: null,
        );
    }

    /**
     * Ручной черновик корзины клиента, созданный менеджером.
     */
    public static function manual(MaxUserIdentity $customer, MaxUserIdentity $manager): self
    {
        return new self(
            ownerMaxUserId: $customer->maxUserId,
            createdByMaxUserId: $manager->maxUserId,
        );
    }
}
