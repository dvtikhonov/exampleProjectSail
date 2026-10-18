<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoMatchIssueCode;

/**
 * Проблемная строка графика для ручной обработки (фрагмент match_result).
 */
readonly class SchedulePhotoScheduleIssueDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public SchedulePhotoMatchIssueCode $code,
        public string $message,
        public string $rawTitle,
        public array $dates = [],
    ) {}

    /**
     * @return array{
     *     code: string,
     *     message: string,
     *     raw_title: string,
     *     dates: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'raw_title' => $this->rawTitle,
            'dates' => $this->dates,
        ];
    }
}
