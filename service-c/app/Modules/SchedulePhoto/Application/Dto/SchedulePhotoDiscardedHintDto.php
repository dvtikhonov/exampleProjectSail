<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Discarded-строка для admin UI: сырой OCR + подсказка канона из словаря/scope.
 */
readonly class SchedulePhotoDiscardedHintDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public string $rawTitle,
        public string $reason,
        public array $dates,
        public ?string $suggestedCanon,
        public bool $suggestedInScope,
        public bool $aliasMissing,
    ) {}

    /**
     * @return array{
     *     raw_title: string,
     *     reason: string,
     *     dates: list<string>,
     *     suggested_canon: string|null,
     *     suggested_in_scope: bool,
     *     alias_missing: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'raw_title' => $this->rawTitle,
            'reason' => $this->reason,
            'dates' => $this->dates,
            'suggested_canon' => $this->suggestedCanon,
            'suggested_in_scope' => $this->suggestedInScope,
            'alias_missing' => $this->aliasMissing,
        ];
    }
}
