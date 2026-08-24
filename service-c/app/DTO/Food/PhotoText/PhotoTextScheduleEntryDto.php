<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

/**
 * Каноническая позиция агента для графика производства: имя блюда и даты доступности.
 */
readonly class PhotoTextScheduleEntryDto
{
    /**
     * @param  list<string>  $dates  даты Y-m-d внутри окна графика
     */
    public function __construct(
        public string $name,
        public array $dates,
    ) {}
}
