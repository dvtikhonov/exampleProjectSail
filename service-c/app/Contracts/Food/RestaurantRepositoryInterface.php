<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Restaurant;

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

    public function findActiveById(int $restaurantId): ?Restaurant;
}
