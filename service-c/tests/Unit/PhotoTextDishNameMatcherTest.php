<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Services\Food\PhotoText\PhotoTextDishNameMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhotoTextDishNameMatcherTest extends TestCase
{
    private DishCatalogRepositoryInterface&MockObject $dishRepository;

    private PhotoTextDishNameMatcher $matcher;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dishRepository = $this->createMock(DishCatalogRepositoryInterface::class);
        $this->matcher = new PhotoTextDishNameMatcher($this->dishRepository);
    }

    /** Блюдо не найдено ни в ресторане, ни в каталоге. */
    public function test_not_found_when_name_is_absent_everywhere(): void
    {
        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->willReturnMap([
                ['Салат', 1, []],
                ['Салат', null, []],
            ]);

        $result = $this->matcher->match('Салат', 1);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->code);
        $this->assertSame('Блюдо не найдено: Салат', $result->message);
    }

    /** Несколько блюд с одним именем в ресторане — ambiguous. */
    public function test_ambiguous_when_multiple_dishes_in_restaurant(): void
    {
        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->with('Салат', 1)
            ->willReturn([
                $this->dish(10, 100, 'Салат'),
                $this->dish(11, 101, 'Салат'),
            ]);

        $result = $this->matcher->match('Салат', 1);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(PhotoTextMatchIssueCode::DishAmbiguous, $result->code);
        $this->assertSame('Найдено несколько блюд: Салат', $result->message);
    }

    /** Блюдо есть в другом ресторане — wrong restaurant. */
    public function test_wrong_restaurant_when_dish_exists_elsewhere(): void
    {
        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->willReturnMap([
                ['Салат', 1, []],
                ['Салат', null, [$this->dish(20, 200, 'Салат')]],
            ]);

        $result = $this->matcher->match('Салат', 1);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->code);
        $this->assertSame('Блюдо не относится к указанному ресторану: Салат', $result->message);
    }

    /** Блюдо в ресторане, но не в указанной категории — wrong category. */
    public function test_wrong_category_when_dish_outside_scope(): void
    {
        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->with('Салат', 1)
            ->willReturn([$this->dish(10, 100, 'Салат')]);

        $result = $this->matcher->match('Салат', 1, [999]);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->code);
        $this->assertSame('Блюдо не относится к указанной категории: Салат', $result->message);
    }

    /** Единственное совпадение в ресторане — success. */
    public function test_success_when_single_dish_matches(): void
    {
        $dish = $this->dish(10, 100, 'Салат');

        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->with('Салат', 1)
            ->willReturn([$dish]);

        $result = $this->matcher->match('Салат', 1);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($dish, $result->dish);
        $this->assertNull($result->code);
        $this->assertNull($result->message);
    }

    /** Единственное совпадение в scope категорий — success. */
    public function test_success_when_single_dish_matches_within_category_scope(): void
    {
        $dish = $this->dish(10, 100, 'Салат');

        $this->dishRepository
            ->method('findByNameCaseInsensitive')
            ->with('Салат', 1)
            ->willReturn([$dish]);

        $result = $this->matcher->match('Салат', 1, [100]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($dish, $result->dish);
    }

    private function dish(int $id, int $menuCategoryId, string $name): DishRecord
    {
        return new DishRecord(
            id: $id,
            menuCategoryId: $menuCategoryId,
            name: $name,
            description: null,
            weight: '200',
            weightUnit: DishWeightUnit::Gram,
            imageUrl: null,
            price: '100.00',
            vatRate: null,
            isAvailable: true,
        );
    }
}
