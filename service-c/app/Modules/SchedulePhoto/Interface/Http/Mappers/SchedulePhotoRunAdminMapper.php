<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Mappers;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoAdminRunViewDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedHintDto;

/**
 * Сериализация admin view прогона в текущий JSON API.
 *
 * Подсказки discarded и сборка catalog_scope — в SchedulePhotoAdminViewBuilder.
 */
final class SchedulePhotoRunAdminMapper
{
    /**
     * Admin JSON: run + enriched discarded + catalog_scope / catalog_error.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(SchedulePhotoAdminRunViewDto $view): array
    {
        $payload = $view->run->toArray();
        $payload['discarded'] = array_values(array_map(
            static fn (SchedulePhotoDiscardedHintDto $row): array => $row->toArray(),
            $view->discarded,
        ));
        $payload['catalog_scope'] = $view->catalogScope === null
            ? null
            : array_values(array_map(
                static fn (SchedulePhotoCatalogScopeDishDto $dish): array => $dish->toArray(),
                $view->catalogScope,
            ));
        $payload['catalog_error'] = $view->catalogError;

        return $payload;
    }
}
