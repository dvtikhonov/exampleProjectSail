<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Колбэк sidecar после vision OCR (успех с entries или ошибка).
 */
readonly class SchedulePhotoOcrCallbackDto
{
    /**
     * @param  list<array{raw_title: string, dates: list<string>}>|null  $entries
     * @param  list<SchedulePhotoDiscardedRowDto>  $discarded  строки, отброшенные LLM до match; dates — дни с фото
     */
    public function __construct(
        public string $runId,
        public ?array $entries = null,
        public ?string $error = null,
        public array $discarded = [],
    ) {}
}