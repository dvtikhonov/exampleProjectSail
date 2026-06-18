<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\DTO\YandexMaps\OrganizationCandidateDto;

/**
 * Maps Yandex Maps JSON business records to organization candidates.
 */
class OrganizationRecordMapper
{
    /** @var string[] */
    private const HREF_KEYS = ['uri', 'url', 'link', 'permalink', 'canonicalUrl'];

    /** @var string[] */
    private const ORG_NAME_KEYS = ['shortName', 'name', 'title', 'caption', 'fullName'];

    public function __construct(
        private readonly JsonTreeWalker $jsonTreeWalker,
        private readonly YandexUrlHelper $urlHelper,
    ) {}

    /**
     * Reject tab-navigation text accidentally scraped as an organization name.
     */
    public function isPlausibleOrgName(string $name): bool
    {
        $trimmed = trim($name);

        if ($trimmed === '' || mb_strlen($trimmed) > 100) {
            return false;
        }

        if (preg_match('/Обзор.*(?:Товары|Фото|Отзывы|Филиалы|Особенности)/iu', $trimmed) === 1) {
            return false;
        }

        if (
            preg_match('/^(Обзор|Товары и услуги|Новости\d*|Фото\d*|Отзывы\d*|Филиалы|Особенности)/iu', $trimmed) === 1
            && mb_strlen($trimmed) > 25
        ) {
            return false;
        }

        return true;
    }

    /**
     * Extract organization id only from /org/.../{id} paths in href-like fields.
     *
     * @param  array<string, mixed>  $record
     */
    public function pickOrgIdFromHref(array $record): ?string
    {
        foreach (self::HREF_KEYS as $key) {
            $value = $this->jsonTreeWalker->pickString($record, [$key]);

            if ($value === null) {
                continue;
            }

            $orgId = $this->urlHelper->extractOrgIdFromHref($value)
                ?? $this->urlHelper->extractOrgIdFromUrl($value);

            if ($orgId !== null) {
                return $orgId;
            }
        }

        return null;
    }

    /**
     * Check whether a JSON record belongs to the requested organization.
     *
     * @param  array<string, mixed>  $record
     */
    public function recordMatchesOrgId(array $record, string $orgId): bool
    {
        $fromHref = $this->pickOrgIdFromHref($record);

        if ($fromHref !== null) {
            return $fromHref === $orgId;
        }

        $direct = $record['id'] ?? $record['orgId'] ?? $record['businessId'] ?? $record['companyId'] ?? null;
        $idStr = $direct === null ? null : (string) $direct;

        if ($idStr !== $orgId) {
            return false;
        }

        return $this->jsonTreeWalker->pickRecord($record, ['ratingData', 'rating', 'stars']) !== null
            || $this->jsonTreeWalker->pickString($record, ['address', 'fullAddress', 'formattedAddress']) !== null
            || $this->jsonTreeWalker->pickString($record, self::ORG_NAME_KEYS) !== null;
    }

    /**
     * Build a candidate from a Yandex Maps JSON business record.
     *
     * @param  array<string, mixed>  $record
     */
    public function mapRecordToCandidate(array $record, string $origin): ?OrganizationCandidateDto
    {
        $orgId = $this->pickOrgIdFromHref($record);

        if ($orgId === null) {
            return null;
        }

        $name = $this->jsonTreeWalker->pickString($record, self::ORG_NAME_KEYS);

        if ($name === null || ! $this->isPlausibleOrgName($name)) {
            return null;
        }

        $slug = $this->pickOrgSlug($record);
        $address = $this->jsonTreeWalker->pickString($record, ['address', 'fullAddress', 'formattedAddress', 'subtitle']) ?? '';
        $ratingFields = $this->extractRatingFields($record);

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: $address,
            averageRating: $ratingFields['average_rating'],
            reviewsCount: $ratingFields['reviews_count'],
            ratingsCount: $ratingFields['ratings_count'],
            canonicalUrl: "{$origin}/maps/org/{$slug}/{$orgId}/",
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{average_rating: ?float, reviews_count: ?int, ratings_count: ?int}
     */
    public function extractRatingFields(array $record): array
    {
        $ratingRecord = $this->jsonTreeWalker->pickRecord($record, ['ratingData', 'rating', 'stars', 'businessRating']);

        return [
            'average_rating' => $this->urlHelper->parseRating(
                $record['rating']
                ?? $record['score']
                ?? $record['averageRating']
                ?? $this->pickNestedValue($ratingRecord, 'score')
                ?? $this->pickNestedValue($ratingRecord, 'rating')
                ?? $this->pickNestedValue($ratingRecord, 'value')
                ?? $this->pickNestedValue($ratingRecord, 'avgRating'),
            ),
            'reviews_count' => $this->urlHelper->parseCount(
                $record['reviewsCount']
                ?? $record['reviews']
                ?? $record['reviewCount']
                ?? $this->pickNestedValue($ratingRecord, 'reviewsCount')
                ?? $this->pickNestedValue($ratingRecord, 'reviews')
                ?? $this->pickNestedValue($ratingRecord, 'reviewCount'),
            ),
            'ratings_count' => $this->urlHelper->parseCount(
                $record['ratingsCount']
                ?? $record['ratings']
                ?? $record['ratingCount']
                ?? $this->pickNestedValue($ratingRecord, 'ratingsCount')
                ?? $this->pickNestedValue($ratingRecord, 'ratings')
                ?? $this->pickNestedValue($ratingRecord, 'ratingCount'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private function pickNestedValue(?array $record, string $key): mixed
    {
        if ($record === null) {
            return null;
        }

        return $record[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function pickOrgSlug(array $record): string
    {
        foreach (self::HREF_KEYS as $key) {
            $value = $this->jsonTreeWalker->pickString($record, [$key]);

            if ($value === null) {
                continue;
            }

            if (preg_match('/\/org\/([^\/]+)\/\d+/i', $value, $matches) === 1) {
                return $matches[1];
            }
        }

        return $this->jsonTreeWalker->pickString($record, ['slug', 'seoSlug']) ?? 'organization';
    }
}
