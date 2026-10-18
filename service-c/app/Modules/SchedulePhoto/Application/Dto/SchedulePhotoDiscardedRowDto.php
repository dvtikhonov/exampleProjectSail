<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Строка discarded (LLM до match): raw_title, reason, даты с фото.
 */
readonly class SchedulePhotoDiscardedRowDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public string $rawTitle,
        public string $reason,
        public array $dates = [],
    ) {}

    /**
     * @return array{raw_title: string, reason: string, dates: list<string>}
     */
    public function toArray(): array
    {
        return [
            'raw_title' => $this->rawTitle,
            'reason' => $this->reason,
            'dates' => $this->dates,
        ];
    }
}
