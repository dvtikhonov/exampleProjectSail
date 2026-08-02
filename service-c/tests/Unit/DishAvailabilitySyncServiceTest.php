<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\Menu\Weekday;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\MenuCategory;
use App\Models\Food\MenuCategoryAvailabilityOffset;
use App\Services\Food\Menu\DishAvailabilitySyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Unit-тесты sync is_available по offsets категорий на weekday.
 */
class DishAvailabilitySyncServiceTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /**
     * Сначала сброс is_available у всех; затем каждая категория — по своей дате
     * (сегодня + offset). Блюда категорий без offset остаются выключенными.
     */
    public function test_sync_by_category_offsets_clears_all_then_enables_by_category_date(): void
    {
        $now = CarbonImmutable::parse('2026-07-31 03:00:00', 'Europe/Moscow'); // Friday
        $tomorrow = '2026-08-01';
        $dayAfter = '2026-08-02';

        $catA = FoodTestDataBuilder::createRestaurantWithDish('Cafe A', 'Блюдо A');
        $catA['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $catA['dish']->id,
            'available_date' => $tomorrow,
        ]);
        $this->createOffset((int) $catA['category']->id, Weekday::Friday, 1);

        $restaurantB = $catA['restaurant'];
        $categoryB = MenuCategory::factory()->create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'Side',
            'sort_order' => 2,
        ]);
        $dishB = Dish::factory()->create([
            'menu_category_id' => $categoryB->id,
            'name' => 'Блюдо B',
            'is_available' => false,
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $dishB->id,
            'available_date' => $dayAfter,
        ]);
        $this->createOffset((int) $categoryB->id, Weekday::Friday, 2);

        $withoutOffset = FoodTestDataBuilder::createRestaurantWithDish('Cafe C', 'Без offset');
        $withoutOffset['dish']->update(['is_available' => true]);

        $updated = app(DishAvailabilitySyncService::class)
            ->syncForCurrentWeekdayCategoryOffsets($now);

        $this->assertGreaterThan(0, $updated);
        $this->assertTrue($catA['dish']->fresh()->is_available);
        $this->assertTrue($dishB->fresh()->is_available);
        $this->assertFalse($withoutOffset['dish']->fresh()->is_available);
    }

    /** Блюдо категории с offset без графика на целевую дату выключается. */
    public function test_sync_disables_dish_without_schedule_on_category_date(): void
    {
        $now = CarbonImmutable::parse('2026-07-31 03:00:00', 'Europe/Moscow');

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Выключить');
        $fixture['dish']->update(['is_available' => true]);
        $this->createOffset((int) $fixture['category']->id, Weekday::Friday, 1);

        app(DishAvailabilitySyncService::class)->syncForCurrentWeekdayCategoryOffsets($now);

        $this->assertFalse($fixture['dish']->fresh()->is_available);
    }

    private function createOffset(int $menuCategoryId, Weekday $weekday, int $offsetDays): void
    {
        MenuCategoryAvailabilityOffset::query()->create([
            'menu_category_id' => $menuCategoryId,
            'group_key' => (string) Str::uuid(),
            'weekday' => $weekday,
            'offset_days' => $offsetDays,
        ]);
    }
}
