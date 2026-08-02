<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Food\DishAvailabilityDate;
use App\Repositories\Food\Menu\EloquentDishAvailabilityRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentDishAvailabilitySyncTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Sync включает блюда с датой на сегодня и выключает все остальные. */
    public function test_sync_enables_scheduled_today_and_disables_all_others(): void
    {
        $today = CarbonImmutable::now('Europe/Moscow')->toDateString();
        $tomorrow = CarbonImmutable::now('Europe/Moscow')->addDay()->toDateString();

        $scheduledToday = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Сегодня');
        $scheduledToday['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $scheduledToday['dish']->id,
            'available_date' => $today,
        ]);

        $scheduledOtherDay = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Завтра');
        $scheduledOtherDay['dish']->update(['is_available' => true]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $scheduledOtherDay['dish']->id,
            'available_date' => $tomorrow,
        ]);

        $unscheduled = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Без графика');
        $unscheduled['dish']->update(['is_available' => true]);

        $updated = (new EloquentDishAvailabilityRepository)->syncDishesIsAvailableForDate($today);

        $this->assertGreaterThan(0, $updated);
        $this->assertTrue($scheduledToday['dish']->fresh()->is_available);
        $this->assertFalse($scheduledOtherDay['dish']->fresh()->is_available);
        $this->assertFalse($unscheduled['dish']->fresh()->is_available);
    }

    /** Sync по категории затрагивает только блюда этой категории. */
    public function test_sync_for_category_leaves_other_categories_untouched(): void
    {
        $today = CarbonImmutable::now('Europe/Moscow')->toDateString();

        $target = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Целевая');
        $target['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $target['dish']->id,
            'available_date' => $today,
        ]);

        $other = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Чужая');
        $other['dish']->update(['is_available' => true]);

        $updated = (new EloquentDishAvailabilityRepository)->syncDishesIsAvailableForCategoryAndDate(
            (int) $target['category']->id,
            $today,
        );

        $this->assertGreaterThan(0, $updated);
        $this->assertTrue($target['dish']->fresh()->is_available);
        $this->assertTrue($other['dish']->fresh()->is_available);
    }

    /** clearAllDishesIsAvailable сбрасывает флаг у всех активных блюд. */
    public function test_clear_all_dishes_is_available(): void
    {
        $a = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'A');
        $b = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'B');
        $a['dish']->update(['is_available' => true]);
        $b['dish']->update(['is_available' => true]);

        $updated = (new EloquentDishAvailabilityRepository)->clearAllDishesIsAvailable();

        $this->assertGreaterThan(0, $updated);
        $this->assertFalse($a['dish']->fresh()->is_available);
        $this->assertFalse($b['dish']->fresh()->is_available);
    }
}
