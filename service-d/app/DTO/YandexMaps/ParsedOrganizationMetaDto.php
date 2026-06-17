<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ParsedOrganizationMetaDto
{
    public function __construct(
        public string $orgId,
        public string $name,
        public string $address,
        public ?float $averageRating,
        public ?int $reviewsCount,
        public ?int $ratingsCount,
        public string $canonicalUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromParserArray(array $data): self
    {
        return new self(
            orgId: (string) ($data['org_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            address: (string) ($data['address'] ?? ''),
            averageRating: isset($data['average_rating']) ? (float) $data['average_rating'] : null,
            reviewsCount: isset($data['reviews_count']) ? (int) $data['reviews_count'] : null,
            ratingsCount: isset($data['ratings_count']) ? (int) $data['ratings_count'] : null,
            canonicalUrl: (string) ($data['canonical_url'] ?? ''),
        );
    }
}
