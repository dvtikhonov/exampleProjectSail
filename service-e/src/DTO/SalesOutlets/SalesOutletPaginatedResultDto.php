<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

use App\Domain\SalesOutlet;

/** Страница торговых точек с метаданными пагинации. */
readonly class SalesOutletPaginatedResultDto
{
    /**
     * @param  array<int, SalesOutlet>  $items
     */
    public function __construct(
        public array $items,
        public SalesOutletPaginationDto $pagination,
    ) {}
}
