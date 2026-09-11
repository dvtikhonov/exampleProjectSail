<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\DTO;

/**
 * Отчёт по выручке за период с итогами meta.
 */
readonly class RevenueReportDto
{
    /**
     * @param  list<RevenueDayRowDto>  $days
     */
    public function __construct(
        public array $days,
        public int $metaOrdersCount,
        public string $metaAverageCheck,
        public string $metaAmount,
    ) {}

    /**
     * @return array{
     *     days: list<array{date: string, orders_count: int, average_check: string, amount: string}>,
     *     meta: array{orders_count: int, average_check: string, amount: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'days' => array_map(
                static fn (RevenueDayRowDto $day): array => $day->toArray(),
                $this->days,
            ),
            'meta' => [
                'orders_count' => $this->metaOrdersCount,
                'average_check' => $this->metaAverageCheck,
                'amount' => $this->metaAmount,
            ],
        ];
    }
}
