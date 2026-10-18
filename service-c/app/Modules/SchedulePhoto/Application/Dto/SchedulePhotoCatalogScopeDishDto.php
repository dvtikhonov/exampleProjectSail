<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Блюдо catalog_scope в admin JSON (id + имя + категория).
 */
readonly class SchedulePhotoCatalogScopeDishDto
{
    public function __construct(
        public int $id,
        public string $name,
        public int $categoryId,
        public string $categoryName,
    ) {}

    /**
     * @return array{id: int, name: string, category_id: int, category_name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
        ];
    }
}
