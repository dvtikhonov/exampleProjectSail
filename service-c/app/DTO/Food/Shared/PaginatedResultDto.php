<?php

declare(strict_types=1);

namespace App\DTO\Food\Shared;

/**
 * Доменная пагинация без зависимости от Illuminate LengthAwarePaginator.
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
