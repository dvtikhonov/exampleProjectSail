<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;

/**
 * Проблемная строка PhotoText для ручной обработки.
 */
readonly class PhotoTextIssueDto
{
    public function __construct(
        public PhotoTextMatchIssueCode $code,
        public string $message,
        public string $rawTitle,
        public ?int $quantity = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'raw_title' => $this->rawTitle,
            'quantity' => $this->quantity,
        ];
    }
}
