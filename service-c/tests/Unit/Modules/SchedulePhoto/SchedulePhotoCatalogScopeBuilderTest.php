<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogCategoryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\UseCase\Catalog\SchedulePhotoCatalogScopeBuilder;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
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
        $this->assertInstanceOf(SchedulePhotoCatalogScopeDishDto::class, $scope[0]);
        $this->assertSame(20, $scope[0]->id);
        $this->assertSame('Пюре', $scope[0]->name);
        $this->assertSame(2, $scope[0]->categoryId);
        $this->assertSame('Гарнир', $scope[0]->categoryName);
    }

    #[Test]
    public function rejects_unknown_category(): void
    {
        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->method('getCatalog')->willReturn($this->catalog());

        $builder = new SchedulePhotoCatalogScopeBuilder($photoText);

        $this->expectException(SchedulePhotoDomainException::class);
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

        $this->expectException(SchedulePhotoDomainException::class);
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
