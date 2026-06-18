<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\DomOrgHarvestDto;
use App\DTO\YandexMaps\PageMetaDto;
use App\Services\YandexMaps\Parsing\DomHarvestMapper;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesYandexMapsParsingServices;
use Tests\Support\YandexParserFixtures;

class DomHarvestMapperTest extends TestCase
{
    use CreatesYandexMapsParsingServices;

    private DomHarvestMapper $domHarvestMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domHarvestMapper = $this->createDomHarvestMapper();
    }

    public function test_map_harvest_parses_dom_row_from_fixture(): void
    {
        $fixture = YandexParserFixtures::loadCollect('dom_harvest_invitro');

        $candidate = $this->domHarvestMapper->mapHarvest(
            DomOrgHarvestDto::fromParserArray($fixture),
            'https://yandex.ru',
        );

        $this->assertNotNull($candidate);
        $this->assertSame('Invitro', $candidate->name);
        $this->assertSame('11527230587', $candidate->orgId);
        $this->assertSame(4.4, $candidate->averageRating);
        $this->assertSame(24, $candidate->reviewsCount);
        $this->assertSame(
            'ул. Тореза, 61, Новокузнецк',
            $candidate->address,
        );
        $this->assertSame(
            'https://yandex.ru/maps/org/invitro/11527230587/',
            $candidate->canonicalUrl,
        );
    }

    public function test_map_page_meta_strips_yandex_maps_suffix_from_title(): void
    {
        $candidate = $this->domHarvestMapper->mapPageMeta(
            PageMetaDto::fromParserArray([
                'title' => 'Invitro — Яндекс Карты',
                'header_text' => '4,4 24 отзыва',
                'address_text' => 'ул. Тореза, 61',
            ]),
            'https://yandex.ru/maps/org/invitro/11527230587/',
            '11527230587',
        );

        $this->assertNotNull($candidate);
        $this->assertSame('Invitro', $candidate->name);
        $this->assertSame('ул. Тореза, 61', $candidate->address);
    }
}
