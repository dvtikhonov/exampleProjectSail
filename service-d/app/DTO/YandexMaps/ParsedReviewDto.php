<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

readonly class ParsedReviewDto
{
    public function __construct(
        public string $externalId,
        public string $authorName,
        public ?CarbonInterface $publishedAt,
        public ?string $text,
        public ?int $rating,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromParserArray(array $data): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? ''),
            authorName: (string) ($data['author_name'] ?? ''),
            publishedAt: self::parsePublishedAt($data['published_at'] ?? null),
            text: isset($data['text']) ? (string) $data['text'] : null,
            rating: isset($data['rating']) ? (int) $data['rating'] : null,
        );
    }

    private static function parsePublishedAt(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
