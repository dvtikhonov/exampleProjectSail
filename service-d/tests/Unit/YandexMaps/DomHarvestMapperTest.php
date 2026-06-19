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

    public function test_map_harvest_parses_rating_count_after_decimal_rating(): void
    {
        $candidate = $this->domHarvestMapper->mapHarvest(
            DomOrgHarvestDto::fromParserArray([
                'href' => '/maps/org/invitro/28272397344/',
                'link_text' => 'Invitro',
                'card_text' => 'Invitro 4,6 18 оценок Открыто до 15:00 просп. Запсибовцев, 39/96',
                'rating_aria_label' => '4,6',
                'meta_text' => 'просп. Запсибовцев, 39/96, Новокузнецк',
            ]),
            'https://yandex.ru',
        );

        $this->assertNotNull($candidate);
        $this->assertSame(18, $candidate->ratingsCount);
        $this->assertSame(4.6, $candidate->averageRating);
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

    public function test_map_page_meta_parses_reviews_from_yandex_tab_counters(): void
    {
        $candidate = $this->domHarvestMapper->mapPageMeta(
            PageMetaDto::fromParserArray([
                'title' => 'Invitro — Яндекс Карты',
                'header_text' => 'InvitroОбзорТовары и услугиНовости2Фото11Отзывы24Филиалы',
                'address_text' => 'ул. Тореза, 61, Новокузнецк',
            ]),
            'https://yandex.ru/maps/org/invitro/115272305870/',
            '115272305870',
        );

        $this->assertNotNull($candidate);
        $this->assertSame(24, $candidate->reviewsCount);
    }
}
