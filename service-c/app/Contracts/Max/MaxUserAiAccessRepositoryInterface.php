<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;
use DateTimeInterface;

/**
 * Доступ AI к базе для пользователей MAX mini-app.
 */
interface MaxUserAiAccessRepositoryInterface
{
    /**
     * Очищает просроченный доступ AI (ставит `ai_access_until = null`).
     *
     * @return int Количество обновлённых строк.
     */
    public function clearExpiredAiAccess(DateTimeInterface $now): int;

    /**
     * Находит пользователя с активным доступом AI на момент `now`.
     *
     * @return MaxUserRecord|null Пользователь или `null`, если активного доступа нет.
     */
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUserRecord;

    /**
     * Очищает доступ AI для конкретного пользователя только если он активен на `now`.
     *
     * @return int Количество обновлённых строк.
     */
    public function clearAiAccessForUserIfActive(int $maxUserId, DateTimeInterface $now): int;

    /**
     * Атомарно включает доступ AI до `until` конкретному пользователю, если на `now`
     * нет активного доступа у кого-либо.
     *
     * @return int Количество обновлённых строк (0 или 1).
     */
    public function setAiAccessUntilIfNoneActive(int $maxUserId, DateTimeInterface $until, DateTimeInterface $now): int;
}
