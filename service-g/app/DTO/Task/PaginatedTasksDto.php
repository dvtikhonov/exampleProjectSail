<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Пагинированный список задач для JSON-ответа API.
 */
readonly class PaginatedTasksDto
{
    /**
     * @param  list<TaskDto>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * Собирает DTO из Eloquent-пагинатора.
     *
     * @param  LengthAwarePaginator<int, Task>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator, bool $includeOwnerName): self
    {
        $items = collect($paginator->items())
            ->map(fn (Task $task): TaskDto => TaskDto::fromModel($task, $includeOwnerName))
            ->values()
            ->all();

        return new self(
            items: $items,
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
        );
    }

    /**
     * Преобразует DTO в массив для JSON-ответа.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                fn (TaskDto $dto): array => $dto->toArray(),
                $this->items,
            ),
            'meta' => [
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
            ],
        ];
    }
}
