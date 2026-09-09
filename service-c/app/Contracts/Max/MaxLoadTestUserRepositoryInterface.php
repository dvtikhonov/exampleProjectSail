<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;

/**
 * Пользователи нагрузочного стенда MAX.
 */
interface MaxLoadTestUserRepositoryInterface
{
    /**
     * Создаёт или обновляет пользователя нагрузочного стенда.
     */
    public function upsertLoadTestUser(
        int $maxUserId,
        string $firstName,
        string $username,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;
}
