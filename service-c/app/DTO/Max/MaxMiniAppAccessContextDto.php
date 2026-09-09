<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Контекст доступа к MAX mini-app без секретов (init_data, токены).
 */
readonly class MaxMiniAppAccessContextDto
{
    public function __construct(
        public string $host,
        public bool $isTunnel,
        public ?string $ip,
        public string $userAgent,
        public ?int $initDataLength = null,
    ) {}
}
