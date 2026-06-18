<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ResolveSessionDto;
use App\Exceptions\Organization\OrganizationResolveSessionExpiredException;

interface ResolveSessionStoreInterface
{
    /**
     * @param  OrganizationCandidateDto[]  $candidates
     */
    public function put(
        string $sessionId,
        string $inputUrl,
        string $searchText,
        string $resolvedUrl,
        array $candidates,
        int $ttlSeconds,
    ): void;

    /**
     * @throws OrganizationResolveSessionExpiredException
     */
    public function get(string $sessionId): ResolveSessionDto;
}
