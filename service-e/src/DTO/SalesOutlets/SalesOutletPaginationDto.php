<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

/** Метаданные пагинации списка торговых точек. */
readonly class SalesOutletPaginationDto
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public int $from,
        public int $to,
    ) {
    }

    public static function fromCounts(int $total, int $perPage, int $currentPage): self
    {
        $lastPage = max((int) ceil($total / max($perPage, 1)), 1);
        $from = 0 === $total ? 0 : (($currentPage - 1) * $perPage) + 1;
        $to = 0 === $total ? 0 : min($currentPage * $perPage, $total);

        return new self(
            currentPage: $currentPage,
            lastPage: $lastPage,
            perPage: $perPage,
            total: $total,
            from: $from,
            to: $to,
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
