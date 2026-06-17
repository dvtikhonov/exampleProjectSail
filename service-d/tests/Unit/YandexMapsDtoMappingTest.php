<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\DTO\YandexMaps\ParsedReviewDto;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Tests\Support\YandexParserFixtures;

class YandexMapsDtoMappingTest extends TestCase
{
    public function test_organization_candidate_dto_maps_from_parser_fixture(): void
    {
        $fixture = YandexParserFixtures::load('resolve_single_candidate');
        /** @var array<string, mixed> $raw */
        $raw = $fixture['candidates'][0];

        $dto = OrganizationCandidateDto::fromParserArray($raw);

        $this->assertSame('1248139252', $dto->orgId);
        $this->assertSame('Кафе X', $dto->name);
        $this->assertSame('Москва, ул. Тестовая, 1', $dto->address);
        $this->assertSame(4.8, $dto->averageRating);
        $this->assertSame(587, $dto->reviewsCount);
        $this->assertSame(1200, $dto->ratingsCount);
        $this->assertSame(
            'https://yandex.ru/maps/org/test-cafe/1248139252/',
            $dto->canonicalUrl,
        );

        $this->assertSame([
            'org_id' => '1248139252',
            'name' => 'Кафе X',
            'address' => 'Москва, ул. Тестовая, 1',
            'average_rating' => 4.8,
            'reviews_count' => 587,
            'ratings_count' => 1200,
            'canonical_url' => 'https://yandex.ru/maps/org/test-cafe/1248139252/',
        ], $dto->toArray());
    }

    public function test_parsed_organization_meta_dto_maps_from_sync_fixture(): void
    {
        $fixture = YandexParserFixtures::load('sync_reviews');
        /** @var array<string, mixed> $raw */
        $raw = $fixture['org'];

        $dto = ParsedOrganizationMetaDto::fromParserArray($raw);

        $this->assertSame('1248139252', $dto->orgId);
        $this->assertSame('Кафе X', $dto->name);
        $this->assertSame('Москва, ул. Тестовая, 1', $dto->address);
        $this->assertSame(4.8, $dto->averageRating);
        $this->assertSame(2, $dto->reviewsCount);
        $this->assertSame(1200, $dto->ratingsCount);
        $this->assertSame(
            'https://yandex.ru/maps/org/test-cafe/1248139252/',
            $dto->canonicalUrl,
        );
    }

    public function test_parsed_review_dto_maps_from_sync_fixture(): void
    {
        $fixture = YandexParserFixtures::load('sync_reviews');
        /** @var array<int, array<string, mixed>> $reviews */
        $reviews = $fixture['reviews'];

        $withText = ParsedReviewDto::fromParserArray($reviews[0]);

        $this->assertSame('rev-001', $withText->externalId);
        $this->assertSame('Иван Петров', $withText->authorName);
        $this->assertInstanceOf(Carbon::class, $withText->publishedAt);
        $this->assertSame('2024-06-15', $withText->publishedAt->toDateString());
        $this->assertSame('Отличное место, рекомендую.', $withText->text);
        $this->assertSame(5, $withText->rating);

        $withoutText = ParsedReviewDto::fromParserArray($reviews[1]);

        $this->assertSame('rev-002', $withoutText->externalId);
        $this->assertSame('Мария Сидорова', $withoutText->authorName);
        $this->assertNull($withoutText->text);
        $this->assertSame(4, $withoutText->rating);
    }

    public function test_dto_mapping_handles_missing_optional_fields(): void
    {
        $candidate = OrganizationCandidateDto::fromParserArray([
            'org_id' => 999,
            'name' => 'Только имя',
            'canonical_url' => 'https://yandex.ru/maps/org/only-name/999/',
        ]);

        $this->assertSame('999', $candidate->orgId);
        $this->assertSame('', $candidate->address);
        $this->assertNull($candidate->averageRating);
        $this->assertNull($candidate->reviewsCount);
        $this->assertNull($candidate->ratingsCount);

        $review = ParsedReviewDto::fromParserArray([
            'external_id' => 'minimal',
            'author_name' => 'Гость',
        ]);

        $this->assertSame('minimal', $review->externalId);
        $this->assertSame('Гость', $review->authorName);
        $this->assertNull($review->publishedAt);
        $this->assertNull($review->text);
        $this->assertNull($review->rating);
    }

    public function test_parsed_review_dto_ignores_unparseable_russian_published_at(): void
    {
        $review = ParsedReviewDto::fromParserArray([
            'external_id' => 'Anastasia-быстрый результат анализа,приветливый пе',
            'author_name' => 'Anastasia',
            'published_at' => '30 марта',
            'text' => 'быстрый результат анализа',
            'rating' => null,
        ]);

        $this->assertSame('Anastasia', $review->authorName);
        $this->assertNull($review->publishedAt);
    }
}
