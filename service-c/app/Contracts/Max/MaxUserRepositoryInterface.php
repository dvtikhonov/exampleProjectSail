<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\Models\Max\MaxUser;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Репозиторий пользователей MAX mini-app.
 */
interface MaxUserRepositoryInterface
{
    /**
     * Уникальные max_user_id пользователей с сохранённым адресом доставки.
     *
     * @return list<int>
     */
    public function listMaxUserIdsWithDeliveryAddress(): array;

    /**
     * Находит пользователя по max_user_id.
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUser;

    /**
     * Постраничный поиск пользователей для ручных заказов.
     *
     * @return LengthAwarePaginator<int, MaxUser>
     */
    public function paginateForManualOrders(?string $query, int $perPage): LengthAwarePaginator;

    /**
     * Поиск пользователей по подстроке в first_name / last_name / username (без delivery_address).
     *
     * @return Collection<int, MaxUser>
     */
    public function findByNameFieldsSubstring(string $query): Collection;

    /**
     * Очищает просроченный доступ AI (ставит `ai_access_until = null`).
     *
     * @return int Количество обновлённых строк.
     */
    public function clearExpiredAiAccess(DateTimeInterface $now): int;

    /**
     * Находит пользователя с активным доступом AI на момент `now`.
     *
     * @return MaxUser|null Пользователь или `null`, если активного доступа нет.
     */
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUser;

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
