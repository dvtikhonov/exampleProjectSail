<?php

namespace App\DTO\SalesOutlets;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class SalesOutletPaginationDto
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public int $from,
        public int $to,
    ) {}

    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            from: $paginator->firstItem() ?? 0,
            to: $paginator->lastItem() ?? 0,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
