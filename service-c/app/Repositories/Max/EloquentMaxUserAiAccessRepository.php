<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserAiAccessRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\Models\Max\MaxUser;
use DateTimeInterface;

/**
 * Eloquent-реализация AI-access порта пользователей MAX.
 */
class EloquentMaxUserAiAccessRepository implements MaxUserAiAccessRepositoryInterface
{
    public function __construct(
        private readonly MaxUserMapper $mapper,
    ) {}

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
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUserRecord
    {
        $model = MaxUser::query()
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '>', $now)
            ->orderBy('max_user_id')
            ->first();

        return $model !== null ? $this->mapper->toRecord($model) : null;
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
