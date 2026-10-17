<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Exceptions\Food\FoodDomainException;

/**
 * Scope блюд каталога выбранного origin для промпта OCR, UI и resolve-discarded.
 */
interface SchedulePhotoCatalogScopeBuilderInterface
{
    /**
     * Блюда выбранных категорий ресторана на origin прогона.
     *
     * @param  list<int>  $categoryIds
     * @return list<array{id: int, name: string, category_id: int, category_name: string}> блюда + категория
     *
     * @throws FoodDomainException пустой origin, ресторан или категория не найдены, в категориях нет блюд
     */
    public function build(string $baseUrl, int $restaurantId, array $categoryIds): array;
}
