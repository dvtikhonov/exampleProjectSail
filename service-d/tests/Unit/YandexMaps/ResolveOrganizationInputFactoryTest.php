<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\Services\YandexMaps\OrganizationSearchInputValidator;
use App\Services\YandexMaps\ResolveOrganizationInputFactory;
use Tests\TestCase;

class ResolveOrganizationInputFactoryTest extends TestCase
{
    private ResolveOrganizationInputFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ResolveOrganizationInputFactory(new OrganizationSearchInputValidator);
    }

    public function test_from_url_builds_resolve_dto_for_website_input(): void
    {
        $dto = $this->factory->fromUrl('www.invitro.ru Новокузнецк');

        $this->assertSame('www.invitro.ru Новокузнецк', $dto->inputUrl);
        $this->assertSame('www.invitro.ru Новокузнецк', $dto->searchText);
        $this->assertSame('Новокузнецк', $dto->clarification);
        $this->assertSame(
            'https://yandex.ru/maps/?text=www.invitro.ru%20%D0%9D%D0%BE%D0%B2%D0%BE%D0%BA%D1%83%D0%B7%D0%BD%D0%B5%D1%86%D0%BA',
            $dto->resolverUrl,
        );
    }

    public function test_from_url_builds_resolve_dto_for_direct_yandex_maps_url(): void
    {
        $url = 'https://yandex.ru/maps/org/test-cafe/1248139252/';

        $dto = $this->factory->fromUrl($url);

        $this->assertSame($url, $dto->inputUrl);
        $this->assertSame($url, $dto->resolverUrl);
        $this->assertNull($dto->clarification);
    }
}
