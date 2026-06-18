<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\Services\YandexMaps\ClarificationCandidateFilter;
use PHPUnit\Framework\TestCase;

class ClarificationCandidateFilterTest extends TestCase
{
    private ClarificationCandidateFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new ClarificationCandidateFilter;
    }

    public function test_returns_all_candidates_when_clarification_is_empty(): void
    {
        $candidates = [
            $this->candidate('1', 'Кафе А', 'Москва, ул. Ленина, 1'),
            $this->candidate('2', 'Кафе Б', 'Санкт-Петербург, Невский, 2'),
        ];

        $this->assertSame($candidates, $this->filter->filter($candidates, null));
        $this->assertSame($candidates, $this->filter->filter($candidates, '   '));
    }

    public function test_filters_by_full_clarification_match(): void
    {
        $candidates = [
            $this->candidate('1', 'Инвитро', 'Новокузнецк, проспект Металлургов, 29'),
            $this->candidate('2', 'Инвитро', 'Кемерово, проспект Ленина, 1'),
        ];

        $filtered = $this->filter->filter($candidates, 'Новокузнецк');

        $this->assertCount(1, $filtered);
        $this->assertSame('1', $filtered[0]->orgId);
    }

    public function test_filters_by_street_tokens_in_clarification(): void
    {
        $candidates = [
            $this->candidate('1', 'Инвитро', 'Новокузнецк, проспект Металлургов, 29'),
            $this->candidate('2', 'Инвитро', 'Новокузнецк, проспект Архитекторов, 5'),
        ];

        $filtered = $this->filter->filter(
            $candidates,
            'Новокузнецк проспект Металлургов 29',
        );

        $this->assertCount(1, $filtered);
        $this->assertSame('1', $filtered[0]->orgId);
    }

    public function test_returns_all_when_token_match_is_ambiguous(): void
    {
        $candidates = [
            $this->candidate('1', 'Инвитро', 'Новокузнецк, проспект Металлургов, 29'),
            $this->candidate('2', 'Инвитро', 'Новокузнецк, проспект Архитекторов, 5'),
        ];

        $filtered = $this->filter->filter($candidates, 'Новокузнецк проспект');

        $this->assertCount(2, $filtered);
    }

    private function candidate(string $orgId, string $name, string $address): OrganizationCandidateDto
    {
        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $name,
            address: $address,
            averageRating: null,
            reviewsCount: null,
            ratingsCount: null,
            canonicalUrl: 'https://yandex.ru/maps/org/test/'.$orgId.'/',
        );
    }
}
