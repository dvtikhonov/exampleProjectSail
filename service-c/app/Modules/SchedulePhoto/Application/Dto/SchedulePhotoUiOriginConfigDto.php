<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

/**
 * Конфиг origin’ов для UI schedule-photo (селект + default).
 */
readonly class SchedulePhotoUiOriginConfigDto
{
    /**
     * @param  list<SchedulePhotoUiOriginOptionDto>  $allowedBaseUrls
     */
    public function __construct(
        public string $defaultBaseUrl,
        public array $allowedBaseUrls,
    ) {}

    /**
     * @return array{
     *     default_base_url: string,
     *     allowed_base_urls: list<array{value: string, label: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'default_base_url' => $this->defaultBaseUrl,
            'allowed_base_urls' => array_map(
                static fn (SchedulePhotoUiOriginOptionDto $option): array => $option->toArray(),
                $this->allowedBaseUrls,
            ),
        ];
    }
}
