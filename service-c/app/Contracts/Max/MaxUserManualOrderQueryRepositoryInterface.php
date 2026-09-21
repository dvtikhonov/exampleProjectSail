<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;
use App\DTO\Shared\PaginatedResultDto;

/**
 * Поиск пользователей MAX для ручных заказов.
 */
interface MaxUserManualOrderQueryRepositoryInterface
{
    /**
     * Постраничный поиск пользователей для ручных заказов.
     * Поиск по first_name / last_name / username / max_user_id (без delivery_address).
     *
     * @return PaginatedResultDto<MaxUserRecord>
     */
    public function paginateForManualOrders(?string $query, int $perPage): PaginatedResultDto;

    /**
     * Поиск пользователей по подстроке в first_name / last_name / username (без delivery_address).
     * Не более 3 записей (для resolveExactlyOne: 0 / 1 / >1).
     *
     * @return list<MaxUserRecord>
     */
    public function findByNameFieldsSubstring(string $query): array;
}
