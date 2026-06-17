<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ConfirmOrganizationDto
{
    public function __construct(
        public string $sessionId,
        public string $orgId,
    ) {}
}
