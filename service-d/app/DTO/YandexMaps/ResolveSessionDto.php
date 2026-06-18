<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ResolveSessionDto
{
    /**
     * @param  OrganizationCandidateDto[]  $candidates
     */
    public function __construct(
        public string $inputUrl,
        public string $resolvedUrl,
        public array $candidates,
    ) {}
}
