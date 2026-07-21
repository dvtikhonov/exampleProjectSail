<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\ManualOrderUserDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Поиск клиентов MAX для оформления ручного заказа.
 */
interface ManualOrderUserQueryServiceInterface
{
    /**
     * Постраничный список пользователей для выбора клиента.
     *
     * @return array{
     *     users: list<ManualOrderUserDto>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function list(?string $query, int $perPage): array;

    /**
     * Находит клиента по max_user_id или выбрасывает 404.
     *
     * @throws FoodDomainException
     */
    public function findCustomerOrFail(int $maxUserId): MaxUser;
}
