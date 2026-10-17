<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Human-resolve: отброшенная OCR-строка → канон + даты → повторный match.
 */
readonly class SchedulePhotoResolveDiscardedDto
{
    /**
     * @param  list<string>  $dates  Y-m-d внутри окна прогона
     */
    public function __construct(
        public string $runId,
        public string $rawTitle,
        public array $dates,
        public ?string $dishName = null,
    ) {}
}
