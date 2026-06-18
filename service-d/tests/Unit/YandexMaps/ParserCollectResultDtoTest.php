<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\DomOrgHarvestDto;
use App\DTO\YandexMaps\PageMetaDto;
use App\DTO\YandexMaps\ParserCollectResultDto;
use PHPUnit\Framework\TestCase;

class ParserCollectResultDtoTest extends TestCase
{
    public function test_from_parser_array_maps_collect_payload(): void
    {
        $dto = ParserCollectResultDto::fromParserArray([
            'resolved_url' => 'https://yandex.ru/maps/org/invitro/11527230587/',
            'is_direct_org' => true,
            'direct_org_id' => '11527230587',
            'network_payloads' => [['foo' => 'bar']],
            'dom_harvest' => [[
                'href' => '/maps/org/invitro/11527230587/',
                'link_text' => 'Invitro',
                'card_text' => 'ул. Тореза, 61',
                'rating_aria_label' => 'рейтинг 4,4',
                'meta_text' => 'ул. Тореза, 61, Новокузнецк',
            ]],
            'page_meta' => [
                'title' => 'Invitro — Яндекс Карты',
                'header_text' => '4,4 24 отзыва',
                'address_text' => 'ул. Тореза, 61',
            ],
        ]);

        $this->assertSame('https://yandex.ru/maps/org/invitro/11527230587/', $dto->resolvedUrl);
        $this->assertTrue($dto->isDirectOrg);
        $this->assertSame('11527230587', $dto->directOrgId);
        $this->assertCount(1, $dto->networkPayloads);
        $this->assertCount(1, $dto->domHarvest);
        $this->assertInstanceOf(DomOrgHarvestDto::class, $dto->domHarvest[0]);
        $this->assertInstanceOf(PageMetaDto::class, $dto->pageMeta);
        $this->assertSame('Invitro', $dto->domHarvest[0]->linkText);
        $this->assertSame('Invitro — Яндекс Карты', $dto->pageMeta->title);
    }
}
