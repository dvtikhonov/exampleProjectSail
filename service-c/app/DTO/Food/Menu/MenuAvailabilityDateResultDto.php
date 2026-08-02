<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Результат расчёта даты «Блюда на дату» по offsets категорий меню.
 */
readonly class MenuAvailabilityDateResultDto
{
    /**
     * @param  ?string  $date  Дата в формате Y-m-d либо null при ошибке
     * @param  ?string  $error  Текст ошибки («нет данных») либо null при успехе
     */
    public function __construct(
        public ?string $date,
        public ?string $error,
    ) {}
}
