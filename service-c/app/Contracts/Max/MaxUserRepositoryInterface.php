<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\DTO\Shared\PaginatedResultDto;
use DateTimeInterface;

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
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord;

    /**
     * Создаёт или обновляет профиль пользователя из initData MAX WebApp.
     *
     * @param  int|null  $defaultCustomerCategoryId  категория, если у пользователя ещё нет
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;

    /**
     * Создаёт или обновляет пользователя нагрузочного стенда.
     */
    public function upsertLoadTestUser(
        int $maxUserId,
        string $firstName,
        string $username,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;

    /**
     * Постраничный поиск пользователей для ручных заказов.
     *
     * @return PaginatedResultDto<MaxUserRecord>
     */
    public function paginateForManualOrders(?string $query, int $perPage): PaginatedResultDto;

    /**
     * Поиск пользователей по подстроке в first_name / last_name / username (без delivery_address).
     *
     * @return list<MaxUserRecord>
     */
    public function findByNameFieldsSubstring(string $query): array;

    /**
     * Обновляет адрес доставки пользователя.
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void;

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
