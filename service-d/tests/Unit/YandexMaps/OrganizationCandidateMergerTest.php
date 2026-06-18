<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\Services\YandexMaps\Parsing\OrganizationCandidateMerger;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesYandexMapsParsingServices;

/**
 * Порт кейсов mergeOrganizationCandidate из yandex-parser/tests/orgExtract.test.ts.
 */
class OrganizationCandidateMergerTest extends TestCase
{
    use CreatesYandexMapsParsingServices;

    private OrganizationCandidateMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merger = $this->createOrganizationCandidateMerger();
    }

    public function test_merge_prefers_api_name_over_dom_tab_navigation(): void
    {
        $merged = $this->merger->merge(
            new OrganizationCandidateDto(
                orgId: '11527230587',
                name: 'Invitro',
                address: 'ул. Тореза, 61',
                averageRating: 4.4,
                reviewsCount: 24,
                ratingsCount: 68,
                canonicalUrl: 'https://yandex.ru/maps/org/invitro/11527230587/',
            ),
            new OrganizationCandidateDto(
                orgId: '11527230587',
                name: 'ОбзорТовары и услугиНовости2Фото11Отзывы24ФилиалыОсобенности',
                address: '',
                averageRating: 4.5,
                reviewsCount: 11,
                ratingsCount: null,
                canonicalUrl: 'https://yandex.ru/maps/org/invitro/11527230587/',
            ),
            '11527230587',
        );

        $this->assertNotNull($merged);
        $this->assertSame('Invitro', $merged->name);
        $this->assertSame(4.4, $merged->averageRating);
        $this->assertSame(68, $merged->ratingsCount);
        $this->assertSame(24, $merged->reviewsCount);
    }
}
