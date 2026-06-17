<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ResolveOrganizationDto
{
    public function __construct(
        public string $inputUrl,
        public string $resolverUrl,
        public string $searchText,
        public ?string $clarification,
    ) {}
}
