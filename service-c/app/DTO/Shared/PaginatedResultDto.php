<?php

declare(strict_types=1);

namespace App\DTO\Shared;

/**
 * Постраничный результат без зависимости от Laravel LengthAwarePaginator.
 *
 * @template TItem
 */
readonly class PaginatedResultDto
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
    ) {}
}
