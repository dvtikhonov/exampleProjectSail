<?php

declare(strict_types=1);

namespace App\Contracts\Food\Shared;

use App\Models\Food\Restaurant;

/**
 * Репозиторий ресторанов для клиентского API MAX mini-app.
 */
interface RestaurantRepositoryInterface
{
    /**
     * Активные рестораны, отсортированные по названию.
     *
     * @return list<Restaurant>
     */
    public function findAllActive(): array;

    /**
     * Находит активный ресторан по идентификатору.
     */
    public function findActiveById(int $restaurantId): ?Restaurant;
}
