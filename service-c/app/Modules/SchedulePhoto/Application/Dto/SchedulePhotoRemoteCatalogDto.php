<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Каталог ресторана с GET /api/food/phototext/catalog выбранного origin.
 */
readonly class SchedulePhotoRemoteCatalogDto
{
    /**
     * @param  list<SchedulePhotoRemoteCatalogCategoryDto>  $categories
     */
    public function __construct(
        public int $restaurantId,
        public string $restaurantName,
        public array $categories,
    ) {}
}
