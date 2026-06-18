<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\DTO\YandexMaps\DomOrgHarvestDto;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\PageMetaDto;

/**
 * Maps DOM harvest rows and page metadata to organization candidates.
 */
class DomHarvestMapper
{
    public function __construct(
        private readonly YandexUrlHelper $urlHelper,
        private readonly OrganizationRecordMapper $recordMapper,
    ) {}

    public function mapHarvest(DomOrgHarvestDto $harvest, string $origin): ?OrganizationCandidateDto
    {
        $orgId = $this->urlHelper->extractOrgIdFromHref($harvest->href)
            ?? $this->urlHelper->extractOrgIdFromUrl($harvest->href);

        if ($orgId === null) {
            return null;
        }

        if (preg_match('/\/org\/[^\/]+\/\d+(\/[^\/\?#]+)/i', $harvest->href, $matches) === 1) {
            $extraPath = $matches[1];

            if ($extraPath !== '' && $extraPath !== '/') {
                return null;
            }
        }

        $name = trim($harvest->linkText);

        if (! $this->recordMapper->isPlausibleOrgName($name)) {
            return null;
        }

        $slug = $this->extractSlugFromHref($harvest->href);
        $address = trim($harvest->metaText);

        if ($address === '' || $address === $name) {
            $address = $this->extractAddressFromCardText($harvest->cardText, $name);
        }

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: $address,
            averageRating: $this->urlHelper->parseRating($harvest->ratingAriaLabel),
            reviewsCount: $this->parseReviewsCount($harvest->cardText),
            ratingsCount: $this->parseRatingsCount($harvest->cardText),
            canonicalUrl: "{$origin}/maps/org/{$slug}/{$orgId}/",
        );
    }

    public function mapPageMeta(PageMetaDto $pageMeta, string $resolvedUrl, string $orgId): ?OrganizationCandidateDto
    {
        $name = $this->extractNameFromTitle($pageMeta->title);

        if (! $this->recordMapper->isPlausibleOrgName($name)) {
            return null;
        }

        $origin = $this->urlHelper->safeOrigin($resolvedUrl);
        $slug = $this->extractSlugFromHref($resolvedUrl);

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: trim($pageMeta->addressText),
            averageRating: $this->parseRatingFromText($pageMeta->headerText),
            reviewsCount: $this->parseReviewsCount($pageMeta->headerText),
            ratingsCount: $this->parseRatingsCount($pageMeta->headerText),
            canonicalUrl: "{$origin}/maps/org/{$slug}/{$orgId}/",
        );
    }

    private function extractSlugFromHref(string $href): string
    {
        if (preg_match('/\/org\/([^\/]+)\/\d+/i', $href, $matches) === 1) {
            return $matches[1];
        }

        return 'organization';
    }

    private function extractNameFromTitle(string $title): string
    {
        $name = preg_replace('/\s*—\s*Яндекс\.?\s*Карты.*/iu', '', trim($title)) ?? trim($title);

        return trim($name);
    }

    private function extractAddressFromCardText(string $cardText, string $name): string
    {
        $trimmed = trim($cardText);

        if ($trimmed === '' || $trimmed === $name) {
            return '';
        }

        return $trimmed;
    }

    private function parseRatingFromText(string $text): ?float
    {
        $normalized = str_replace(',', '.', $text);

        if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches) !== 1) {
            return null;
        }

        return $this->urlHelper->parseRating($matches[1]);
    }

    private function parseReviewsCount(string $text): ?int
    {
        if (preg_match('/(\d[\d\s]*)\s*отзыв/iu', $text, $matches) !== 1) {
            return null;
        }

        return $this->urlHelper->parseCount($matches[1]);
    }

    private function parseRatingsCount(string $text): ?int
    {
        if (preg_match('/(\d[\d\s]*)\s*оцен/iu', $text, $matches) !== 1) {
            return null;
        }

        return $this->urlHelper->parseCount($matches[1]);
    }
}
