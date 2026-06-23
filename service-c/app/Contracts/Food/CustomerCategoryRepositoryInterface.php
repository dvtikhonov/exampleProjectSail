<?php

declare(strict_types=1);

namespace App\Contracts\Food;

/**
 * Репозиторий категорий клиентов доставки еды.
 */
interface CustomerCategoryRepositoryInterface
{
    /**
     * Возвращает ID категории по умолчанию для новых клиентов.
     */
    public function findOrCreateDefaultCategoryId(): int;
}
