<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Models\Max\MaxUser;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    /**
     * {@inheritDoc}
     */
    public function findByNameFieldsSubstring(string $query): Collection
    {
        $normalizedQuery = trim($query);

        if ($normalizedQuery === '') {
            return collect();
        }

        $like = '%'.$normalizedQuery.'%';

        return MaxUser::query()
            ->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like);
            })
            ->orderBy('max_user_id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function clearExpiredAiAccess(DateTimeInterface $now): int
    {
        return MaxUser::query()
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '<=', $now)
            ->update(['ai_access_until' => null]);
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUser
    {
        return MaxUser::query()
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '>', $now)
            ->orderBy('max_user_id')
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function clearAiAccessForUserIfActive(int $maxUserId, DateTimeInterface $now): int
    {
        return MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '>', $now)
            ->update(['ai_access_until' => null]);
    }

    /**
     * {@inheritDoc}
     */
    public function setAiAccessUntilIfNoneActive(int $maxUserId, DateTimeInterface $until, DateTimeInterface $now): int
    {
        $table = (new MaxUser)->getTable();

        // Операция атомарна на уровне БД: обновляем строку только если в БД
        // НЕТ ни одной активной записи доступа (NOT EXISTS).
        // MySQL 1093: подзапрос FROM той же таблицы в UPDATE — через derived table.
        return MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->whereRaw(
                "NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM {$table} WHERE ai_access_until > ?) AS active_ai)",
                [$now],
            )
            ->update(['ai_access_until' => $until]);
    }
}
