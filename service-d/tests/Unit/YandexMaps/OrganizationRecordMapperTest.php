<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\Services\YandexMaps\Parsing\OrganizationRecordMapper;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesYandexMapsParsingServices;

/**
 * Порт кейсов из yandex-parser/tests/orgExtract.test.ts для OrganizationRecordMapper.
 */
class OrganizationRecordMapperTest extends TestCase
{
    use CreatesYandexMapsParsingServices;

    private OrganizationRecordMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = $this->createOrganizationRecordMapper();
    }

    public function test_is_plausible_org_name_accepts_regular_business_names(): void
    {
        $this->assertTrue($this->mapper->isPlausibleOrgName('Invitro'));
        $this->assertTrue($this->mapper->isPlausibleOrgName('Кафе X'));
    }

    public function test_is_plausible_org_name_rejects_tab_navigation_concatenation(): void
    {
        $this->assertFalse($this->mapper->isPlausibleOrgName(
            'ОбзорТовары и услугиНовости2Фото11Отзывы24ФилиалыОсобенности',
        ));
    }

    public function test_pick_org_id_from_href_extracts_id_from_uri_field(): void
    {
        $orgId = $this->mapper->pickOrgIdFromHref([
            'uri' => '/maps/org/invitro/11527230587/',
            'name' => 'Invitro',
        ]);

        $this->assertSame('11527230587', $orgId);
    }

    public function test_pick_org_id_from_href_ignores_bare_numeric_id_without_org_path(): void
    {
        $orgId = $this->mapper->pickOrgIdFromHref([
            'id' => 115272305870,
            'name' => 'Wrong entity',
        ]);

        $this->assertNull($orgId);
    }

    public function test_record_matches_org_id_by_href_org_path(): void
    {
        $this->assertTrue($this->mapper->recordMatchesOrgId([
            'uri' => '/maps/org/invitro/11527230587/',
            'name' => 'Invitro',
            'ratingData' => ['score' => 4.4],
        ], '11527230587'));
    }

    public function test_record_matches_org_id_does_not_match_unrelated_numeric_id(): void
    {
        $this->assertFalse($this->mapper->recordMatchesOrgId([
            'id' => 115272305870,
            'name' => 'Tab counter',
        ], '11527230587'));
    }

    public function test_map_record_to_candidate_maps_business_record_from_href(): void
    {
        $candidate = $this->mapper->mapRecordToCandidate([
            'uri' => '/maps/org/invitro/11527230587/',
            'shortName' => 'Invitro',
            'address' => 'ул. Тореза, 61, Новокузнецк',
            'ratingData' => [
                'score' => 4.4,
                'ratingsCount' => 68,
                'reviewsCount' => 24,
            ],
        ], 'https://yandex.ru');

        $this->assertInstanceOf(OrganizationCandidateDto::class, $candidate);
        $this->assertSame('11527230587', $candidate->orgId);
        $this->assertSame('Invitro', $candidate->name);
        $this->assertSame('ул. Тореза, 61, Новокузнецк', $candidate->address);
        $this->assertSame(4.4, $candidate->averageRating);
        $this->assertSame(68, $candidate->ratingsCount);
        $this->assertSame(24, $candidate->reviewsCount);
        $this->assertSame(
            'https://yandex.ru/maps/org/invitro/11527230587/',
            $candidate->canonicalUrl,
        );
    }
}
