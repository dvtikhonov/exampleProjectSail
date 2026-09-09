<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;

/**
 * Идентичность пользователей MAX mini-app (поиск и upsert из initData).
 */
interface MaxUserIdentityRepositoryInterface
{
    /**
     * Находит пользователя по max_user_id.
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord;

    /**
     * Создаёт или обновляет профиль пользователя из initData MAX WebApp.
     *
     * @param  int|null  $defaultCustomerCategoryId  категория, если у пользователя ещё нет
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;
}
