<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Contracts;

use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;

/**
 * Последний заказ пользователя MAX для текста уведомления о входящем сообщении.
 */
interface CustomerLastOrderRepositoryInterface
{
    /**
     * Возвращает последний заказ по max_user_id (created_at DESC) или null.
     */
    public function findLatestByMaxUserId(int $maxUserId): ?LastOrderSummaryDto;
}
