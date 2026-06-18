<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\ParserCollectResultDto;
use App\Services\YandexMaps\Parsing\OrganizationCandidateBuilder;
use Tests\Support\CreatesYandexMapsParsingServices;
use Tests\Support\YandexParserFixtures;
use Tests\TestCase;

class OrganizationCandidateBuilderTest extends TestCase
{
    use CreatesYandexMapsParsingServices;

    private OrganizationCandidateBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->createOrganizationCandidateBuilder();
    }

    public function test_build_merges_network_and_dom_harvest_for_search_fixture(): void
    {
        $collect = ParserCollectResultDto::fromParserArray(
            YandexParserFixtures::loadCollect('search_multi_payload'),
        );

        $candidates = $this->builder->build($collect);

        $this->assertCount(1, $candidates);
        $this->assertSame('Invitro', $candidates[0]->name);
        $this->assertSame('11527230587', $candidates[0]->orgId);
        $this->assertSame(4.4, $candidates[0]->averageRating);
        $this->assertSame(24, $candidates[0]->reviewsCount);
        $this->assertSame(68, $candidates[0]->ratingsCount);
    }

    public function test_build_direct_org_uses_page_meta_fallback_from_fixture(): void
    {
        $collect = ParserCollectResultDto::fromParserArray(
            YandexParserFixtures::loadCollect('direct_org'),
        );

        $candidates = $this->builder->build($collect);

        $this->assertCount(1, $candidates);
        $this->assertSame('11527230587', $candidates[0]->orgId);
        $this->assertSame('Invitro', $candidates[0]->name);
        $this->assertSame('ул. Тореза, 61, Новокузнецк', $candidates[0]->address);
        $this->assertSame(4.4, $candidates[0]->averageRating);
        $this->assertSame(24, $candidates[0]->reviewsCount);
        $this->assertSame(68, $candidates[0]->ratingsCount);
    }
}
