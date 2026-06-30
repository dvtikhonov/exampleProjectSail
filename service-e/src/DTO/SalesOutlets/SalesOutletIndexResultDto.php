<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

use App\Domain\SalesOutlet;

/** Результат запроса списка: строки, пагинация и применённые фильтры. */
readonly class SalesOutletIndexResultDto
{
    /**
     * @param array<int, SalesOutlet> $salesOutlets
     */
    public function __construct(
        public array $salesOutlets,
        public SalesOutletPaginationDto $pagination,
        public SalesOutletIndexQueryDto $query,
    ) {
    }

    public static function fromPaginatedResult(
        SalesOutletPaginatedResultDto $paginatedResult,
        SalesOutletIndexQueryDto $query,
    ): self {
        return new self(
            salesOutlets: $paginatedResult->items,
            pagination: $paginatedResult->pagination,
            query: $query,
        );
    }
}
