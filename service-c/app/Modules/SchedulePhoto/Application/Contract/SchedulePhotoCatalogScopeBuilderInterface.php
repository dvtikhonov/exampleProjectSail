<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Scope блюд каталога выбранного origin для промпта OCR, UI и resolve-discarded.
 */
interface SchedulePhotoCatalogScopeBuilderInterface
{
    /**
     * Блюда выбранных категорий ресторана на origin прогона.
     *
     * @param  list<int>  $categoryIds
     * @return list<SchedulePhotoCatalogScopeDishDto> блюда + категория
     *
     * @throws SchedulePhotoDomainException пустой origin, ресторан или категория не найдены, в категориях нет блюд
     */
    public function build(string $baseUrl, int $restaurantId, array $categoryIds): array;
}
