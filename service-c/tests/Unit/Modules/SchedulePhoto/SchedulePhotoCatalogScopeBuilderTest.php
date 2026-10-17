<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteCatalogCategoryDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteCatalogDishDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteCatalogDto;
use App\Modules\SchedulePhoto\Services\SchedulePhotoCatalogScopeBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoCatalogScopeBuilderTest extends TestCase
{
    private const string BASE_URL = 'http://localhost:8083';

    #[Test]
    public function builds_dishes_for_selected_categories(): void
    {
        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->once())
            ->method('getCatalog')
            ->with(self::BASE_URL, 1)
            ->willReturn($this->catalog());

        $builder = new SchedulePhotoCatalogScopeBuilder($photoText);
        $scope = $builder->build(self::BASE_URL, 1, [2]);

        $this->assertCount(1, $scope);
        $this->assertSame(20, $scope[0]['id']);
        $this->assertSame('Пюре', $scope[0]['name']);
        $this->assertSame(2, $scope[0]['category_id']);
        $this->assertSame('Гарнир', $scope[0]['category_name']);
    }

    #[Test]
    public function rejects_unknown_category(): void
    {
        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->method('getCatalog')->willReturn($this->catalog());

        $builder = new SchedulePhotoCatalogScopeBuilder($photoText);

        $this->expectException(FoodDomainException::class);
        $builder->build(self::BASE_URL, 1, [99]);
    }

    #[Test]
    public function rejects_catalog_of_another_restaurant(): void
    {
        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->method('getCatalog')->willReturn(new SchedulePhotoRemoteCatalogDto(
            restaurantId: 7,
            restaurantName: 'Другой',
            categories: [
                new SchedulePhotoRemoteCatalogCategoryDto(2, 'Гарнир', [
                    new SchedulePhotoRemoteCatalogDishDto(20, 'Пюре'),
                ]),
            ],
        ));

        $builder = new SchedulePhotoCatalogScopeBuilder($photoText);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Ресторан не найден на выбранном сервере.');
        $builder->build(self::BASE_URL, 1, [2]);
    }

    private function catalog(): SchedulePhotoRemoteCatalogDto
    {
        return new SchedulePhotoRemoteCatalogDto(
            restaurantId: 1,
            restaurantName: 'Столовая',
            categories: [
                new SchedulePhotoRemoteCatalogCategoryDto(1, 'Супы', [
                    new SchedulePhotoRemoteCatalogDishDto(10, 'Борщ'),
                ]),
                new SchedulePhotoRemoteCatalogCategoryDto(2, 'Гарнир', [
                    new SchedulePhotoRemoteCatalogDishDto(20, 'Пюре'),
                ]),
            ],
        );
    }
}
