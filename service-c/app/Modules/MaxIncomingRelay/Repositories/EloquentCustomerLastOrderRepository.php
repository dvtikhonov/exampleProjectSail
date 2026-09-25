<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Repositories;

use App\Models\Food\FoodOrder;
use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;
use DateTimeImmutable;

/**
 * Eloquent-репозиторий последнего заказа пользователя (max_food_orders).
 */
final class EloquentCustomerLastOrderRepository implements CustomerLastOrderRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findLatestByMaxUserId(int $maxUserId): ?LastOrderSummaryDto
    {
        $order = FoodOrder::query()
            ->where('max_user_id', $maxUserId)
            ->orderByDesc('created_at')
            ->first(['id', 'created_at']);

        if ($order === null || $order->created_at === null) {
            return null;
        }

        return new LastOrderSummaryDto(
            id: (int) $order->id,
            createdAt: DateTimeImmutable::createFromInterface($order->created_at),
        );
    }
}
