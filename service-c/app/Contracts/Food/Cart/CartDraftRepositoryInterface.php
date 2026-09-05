<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartRecord;

/**
 * Черновики корзины (личные и ручные).
 */
interface CartDraftRepositoryInterface
{
    /**
     * Личный черновик корзины клиента (created_by_max_user_id IS NULL).
     */
    public function findDraftByMaxUserId(int $maxUserId): ?CartRecord;

    /**
     * Личный черновик корзины с блокировкой строки для обновления (SELECT … FOR UPDATE).
     */
    public function findDraftForUpdate(int $maxUserId): ?CartRecord;

    /**
     * Ручной черновик корзины клиента, созданный менеджером.
     */
    public function findManualDraft(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord;

    /**
     * Ручной черновик корзины с блокировкой строки для обновления.
     */
    public function findManualDraftForUpdate(int $customerMaxUserId, int $managerMaxUserId): ?CartRecord;

    /**
     * Создаёт черновик корзины.
     */
    public function createDraft(CartCreateCommand $command): CartRecord;
}
