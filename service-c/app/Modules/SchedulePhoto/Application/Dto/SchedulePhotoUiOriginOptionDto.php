<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Опция origin в селекте UI schedule-photo.
 */
readonly class SchedulePhotoUiOriginOptionDto
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
        ];
    }
}
