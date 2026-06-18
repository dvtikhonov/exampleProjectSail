<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\DTO\YandexMaps\ParsedReviewDto;
use App\DTO\YandexMaps\ParserCollectResultDto;

interface YandexMapsClientInterface
{
    public function collect(string $url): ParserCollectResultDto;

    /**
     * @param  string[]  $stopAnchors
     * @return array{org: ParsedOrganizationMetaDto, reviews: ParsedReviewDto[]}
     */
    public function syncReviews(string $orgId, string $canonicalUrl, array $stopAnchors = []): array;
}
