<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Результат очистки заказов и корзин load-test пользователей.
 */
readonly class LoadTestCleanupResultDto
{
    /**
     * @param  list<int>  $maxUserIds
     */
    public function __construct(
        public int $ordersDeleted,
        public int $cartsDeleted,
        public array $maxUserIds,
    ) {}
}
