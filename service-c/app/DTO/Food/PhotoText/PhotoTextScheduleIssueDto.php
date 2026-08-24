<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;

/**
 * Проблемная строка графика PhotoText для ручной обработки.
 */
readonly class PhotoTextScheduleIssueDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public PhotoTextMatchIssueCode $code,
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
