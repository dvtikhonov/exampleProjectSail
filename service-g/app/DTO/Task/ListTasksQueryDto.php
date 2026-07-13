<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Enums\TaskStatus;

/**
 * Параметры списка задач (фильтрация, поиск, сортировка, пагинация).
 */
readonly class ListTasksQueryDto
{
    public function __construct(
        public ?TaskStatus $status,
        public ?string $search,
        public string $sort,
        public string $direction,
        public int $page,
        public int $perPage,
    ) {}
}
