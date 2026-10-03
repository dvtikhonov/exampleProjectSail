<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Сбор source-линий меню ресторана для синхронизации с Briskly.
 */
interface BrisklySyncSourceCollectorInterface
{
    /**
     * Собирает доступные позиции меню выбранного активного ресторана.
     *
     * Фильтры:
     * - vps_category_id — оставить линии, у которых хотя бы одно блюдо из категории;
     * - search_text — подстрока в display_name (без учёта регистра).
     *
     * @return list<SourceMenuLineDto>
     *
     * @throws FoodDomainException ресторан не найден/неактивен или категория чужая ресторану
     */
    public function collectForRestaurant(
        int $restaurantId,
        ?int $vpsCategoryId = null,
        ?string $searchText = null,
    ): array;
}
