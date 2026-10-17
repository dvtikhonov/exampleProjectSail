<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Блюдо каталога PhotoText на выбранном origin.
 */
readonly class SchedulePhotoRemoteCatalogDishDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
