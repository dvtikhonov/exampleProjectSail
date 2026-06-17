<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\DTO\YandexMaps\ParsedReviewDto;

interface YandexMapsClientInterface
{
    /**
     * @return array{resolved_url: string, candidates: OrganizationCandidateDto[]}
     */
    public function resolve(string $url): array;

    /**
     * @return array{org: ParsedOrganizationMetaDto, reviews: ParsedReviewDto[]}
     */
    public function syncReviews(string $orgId, string $canonicalUrl): array;
}
