<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\DishAvailabilityScheduleWriterInterface;
use App\Contracts\Food\Menu\DishAvailabilitySyncServiceInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\DTO\Food\Menu\DishAvailabilityUpdateDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\MenuCategory;
use App\Services\Food\PhotoText\PhotoTextScheduleApplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextScheduleApplierTest extends TestCase
{
    use ResetsFoodDomainTables;

    private const string TIMEZONE = 'Europe/Moscow';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        // Изолируем откат графика от побочных DB-эффектов weekday sync / cache.
        $this->mock(DishAvailabilitySyncServiceInterface::class)->shouldIgnoreMissing();
        $this->mock(MenuCatalogCacheInvalidatorInterface::class)->shouldIgnoreMissing();
    }

    /** Сбой на 2-й категории откатывает запись 1-й (одна TX на multi-category apply). */
    public function test_apply_rolls_back_first_category_when_second_fails(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Rollback Cafe', 'Борщ', 150);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dateFrom = '2026-08-20';
        $dateTo = '2026-08-26';

        $realWriter = $this->app->make(DishAvailabilityScheduleWriterInterface::class);
        $calls = (object) ['count' => 0];
        $this->app->instance(
            DishAvailabilityScheduleWriterInterface::class,
            new class($realWriter, $calls) implements DishAvailabilityScheduleWriterInterface
            {
                /**
                 * @param  object{count: int}  $calls
                 */
                public function __construct(
                    private readonly DishAvailabilityScheduleWriterInterface $inner,
                    private readonly object $calls,
                ) {}

                public function syncSchedule(DishAvailabilityUpdateDto $dto): void
                {
                    $this->calls->count++;

                    if ($this->calls->count >= 2) {
                        throw new RuntimeException('Forced failure on second category');
                    }

                    $this->inner->syncSchedule($dto);
                }
            },
        );

        $applier = $this->app->make(PhotoTextScheduleApplier::class);

        try {
            $applier->apply(
                (int) $fixture['restaurant']->id,
                [(int) $fixture['category']->id, (int) $sideCategory->id],
                $dateFrom,
                $dateTo,
                [
                    new PhotoTextScheduleEntryDto(name: 'Борщ', dates: [$dateFrom]),
                    new PhotoTextScheduleEntryDto(name: 'Гречка', dates: ['2026-08-21']),
                ],
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced failure on second category', $exception->getMessage());
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DishAvailabilityDate::query()->count());
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dateFrom,
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $sideDish->id,
            'available_date' => '2026-08-21',
        ]);
    }
}
