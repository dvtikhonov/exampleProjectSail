<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishRecord;

/**
 * Пакетные операции над блюдами (импорт и массовые апдейты).
 */
interface DishAdminBulkRepositoryInterface
{
    /**
     * Пакетно обновляет цены блюд по id.
     *
     * @param  array<int, string>  $pricesById  dishId => price
     */
    public function updatePricesByIds(array $pricesById): void;

    /**
     * Пакетно создаёт блюда и возвращает созданные проекции с id.
     *
     * @param  list<CreateDishDto>  $dtos
     * @return list<DishRecord>
     */
    public function createMany(array $dtos): array;

    /**
     * Пакетно обновляет image_url блюд по id.
     *
     * @param  array<int, string>  $imageUrlsById  dishId => image_url
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void;
}
