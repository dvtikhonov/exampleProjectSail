<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\DTO;

/**
 * Строка дневной выручки (только confirmed-заказы).
 */
readonly class RevenueDayRowDto
{
    public function __construct(
        public string $date,
        public int $ordersCount,
        public string $averageCheck,
        public string $amount,
    ) {}

    /**
     * @return array{date: string, orders_count: int, average_check: string, amount: string}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'orders_count' => $this->ordersCount,
            'average_check' => $this->averageCheck,
            'amount' => $this->amount,
        ];
    }
}
