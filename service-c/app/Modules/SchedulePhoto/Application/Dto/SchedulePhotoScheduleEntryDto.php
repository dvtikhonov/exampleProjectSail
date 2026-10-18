<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Позиция графика для match/apply: имя блюда и даты доступности.
 */
readonly class SchedulePhotoScheduleEntryDto
{
    /**
     * @param  list<string>  $dates  даты Y-m-d внутри окна графика
     */
    public function __construct(
        public string $name,
        public array $dates,
    ) {}
}
