<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Admin;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoUiOriginConfigBuilderInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoUiOriginConfigDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoUiOriginOptionDto;

/**
 * Чистая сборка UI origin-опций: фильтр localhost, лейблы Docker/VPS, выбор default.
 *
 * Значения URL биндятся из ServiceProvider — без config() внутри сервиса.
 */
final class SchedulePhotoUiOriginConfigBuilder implements SchedulePhotoUiOriginConfigBuilderInterface
{
    /**
     * @param  list<string>  $allowedBaseUrls
     */
    public function __construct(
        private readonly string $localBaseUrl,
        private readonly string $defaultBaseUrl,
        private readonly array $allowedBaseUrls,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(): SchedulePhotoUiOriginConfigDto
    {
        $local = rtrim($this->localBaseUrl, '/');
        $default = rtrim($this->defaultBaseUrl, '/');

        /** @var list<SchedulePhotoUiOriginOptionDto> $uiOptions */
        $uiOptions = [];
        $seen = [];

        foreach ($this->allowedBaseUrls as $origin) {
            if (trim($origin) === '') {
                continue;
            }

            $value = rtrim(trim($origin), '/');
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: ''));
            if ($host === 'localhost' || $host === '127.0.0.1') {
                continue;
            }

            $port = parse_url($value, PHP_URL_PORT);
            $hostLabel = $host.($port !== null && $port !== false ? ':'.$port : '');
            $isDockerLocal = $host === 'host.docker.internal';

            $seen[$value] = true;
            $uiOptions[] = new SchedulePhotoUiOriginOptionDto(
                value: $value,
                label: $isDockerLocal
                    ? 'Локальный ('.$hostLabel.')'
                    : 'VPS ('.$hostLabel.')',
            );
        }

        $defaultForUi = isset($seen[$default])
            ? $default
            : (isset($seen[$local]) ? $local : ($uiOptions[0]->value ?? $default));

        return new SchedulePhotoUiOriginConfigDto(
            defaultBaseUrl: $defaultForUi,
            allowedBaseUrls: $uiOptions,
        );
    }
}
