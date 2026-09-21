<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Models\Max\MaxUser;

/**
 * Eloquent-реализация delivery-порта пользователей MAX.
 */
class EloquentMaxUserDeliveryRepository implements MaxUserDeliveryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function listMaxUserIdsWithDeliveryAddress(): array
    {
        return MaxUser::query()
            ->whereNotNull('delivery_address')
            ->whereRaw("TRIM(delivery_address) <> ''")
            ->orderBy('max_user_id')
            ->pluck('max_user_id')
            ->map(static fn (mixed $maxUserId): int => (int) $maxUserId)
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void
    {
        MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->update(['delivery_address' => $deliveryAddress]);
    }
}
