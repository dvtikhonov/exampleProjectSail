<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Категория каталога PhotoText и её блюда.
 */
readonly class SchedulePhotoRemoteCatalogCategoryDto
{
    /**
     * @param  list<SchedulePhotoRemoteCatalogDishDto>  $dishes
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $dishes,
    ) {}
}
