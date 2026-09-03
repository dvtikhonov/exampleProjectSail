<?php

declare(strict_types=1);

namespace App\Contracts\Food\Delivery;

use App\DTO\Food\Delivery\CustomerCategoryDto;

/**
 * Репозиторий категорий клиентов доставки еды.
 */
interface CustomerCategoryRepositoryInterface
{
    /**
     * Возвращает ID категории по умолчанию для новых клиентов.
     */
    public function findOrCreateDefaultCategoryId(): int;

    /**
     * Категория клиента по max_user_id или null, если не назначена.
     */
    public function findCategoryForMaxUserId(int $maxUserId): ?CustomerCategoryDto;
}
