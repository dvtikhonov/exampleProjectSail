<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Models\MaxUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация репозитория пользователей MAX.
 */
class EloquentMaxUserRepository implements MaxUserRepositoryInterface
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
    public function findByMaxUserId(int $maxUserId): ?MaxUser
    {
        return MaxUser::query()->find($maxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForManualOrders(?string $query, int $perPage): LengthAwarePaginator
    {
        $builder = MaxUser::query()->orderBy('max_user_id');

        $normalizedQuery = $query !== null ? trim($query) : '';

        if ($normalizedQuery !== '') {
            $like = '%'.$normalizedQuery.'%';

            $builder->where(function (Builder $searchQuery) use ($normalizedQuery, $like): void {
                $searchQuery
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('delivery_address', 'like', $like);

                if (ctype_digit($normalizedQuery)) {
                    $searchQuery->orWhere('max_user_id', (int) $normalizedQuery);
                }
            });
        }

        return $builder->paginate($perPage);
    }
}
