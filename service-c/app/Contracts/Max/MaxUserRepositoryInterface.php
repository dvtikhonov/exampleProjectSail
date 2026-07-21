<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\Models\MaxUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
