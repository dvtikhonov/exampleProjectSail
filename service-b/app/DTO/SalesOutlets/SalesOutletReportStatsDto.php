<?php

namespace App\DTO\SalesOutlets;

readonly class SalesOutletReportStatsDto
{
    /**
     * @param  array<string, array{pending: int, processing: int, completed: int, failed: int, total: int}>  $byType
     */
    public function __construct(
        public array $byType,
        public string $generatedAt,
    ) {}

    /**
     * @return array{by_type: array<string, array{pending: int, processing: int, completed: int, failed: int, total: int}>, generated_at: string}
     */
    public function toArray(): array
    {
        return [
            'by_type' => $this->byType,
            'generated_at' => $this->generatedAt,
        ];
    }
}
