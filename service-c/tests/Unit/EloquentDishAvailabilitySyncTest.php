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

    /** Репозиторий через контейнер (нужен DishMapper в конструкторе). */
    private function repository(): EloquentDishAvailabilityRepository
    {
        return $this->app->make(EloquentDishAvailabilityRepository::class);
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

        $updated = $this->repository()->syncDishesIsAvailableForDate($today);

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

        $updated = $this->repository()->syncDishesIsAvailableForCategoryAndDate(
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

        $updated = $this->repository()->clearAllDishesIsAvailable();

        $this->assertGreaterThan(0, $updated);
        $this->assertFalse($a['dish']->fresh()->is_available);
        $this->assertFalse($b['dish']->fresh()->is_available);
    }

    /** Пакетный sync графика заменяет даты в диапазоне для нескольких блюд. */
    public function test_sync_dishes_availability_in_range_batches_replace(): void
    {
        $today = CarbonImmutable::now('Europe/Moscow')->toDateString();
        $futureA = CarbonImmutable::now('Europe/Moscow')->addDays(3)->toDateString();
        $futureB = CarbonImmutable::now('Europe/Moscow')->addDays(4)->toDateString();
        $rangeTo = CarbonImmutable::now('Europe/Moscow')->addDays(30)->toDateString();

        $dishA = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'A');
        $dishB = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'B');

        DishAvailabilityDate::query()->create([
            'dish_id' => $dishA['dish']->id,
            'available_date' => $futureA,
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $dishA['dish']->id,
            'available_date' => $futureB,
        ]);

        $this->repository()->syncDishesAvailabilityInRange(
            [
                (int) $dishA['dish']->id => [$futureA],
                (int) $dishB['dish']->id => [$futureB],
            ],
            $today,
            $rangeTo,
            $today,
        );

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dishA['dish']->id,
            'available_date' => $futureA,
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $dishA['dish']->id,
            'available_date' => $futureB,
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dishB['dish']->id,
            'available_date' => $futureB,
        ]);
    }

    /** enableDishesIsAvailableForCategoryDates включает блюда по разным датам категорий. */
    public function test_enable_dishes_is_available_for_category_dates(): void
    {
        $tomorrow = CarbonImmutable::now('Europe/Moscow')->addDay()->toDateString();
        $dayAfter = CarbonImmutable::now('Europe/Moscow')->addDays(2)->toDateString();

        $catA = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'A');
        $catA['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $catA['dish']->id,
            'available_date' => $tomorrow,
        ]);

        $catB = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'B');
        $catB['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $catB['dish']->id,
            'available_date' => $dayAfter,
        ]);

        $other = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Other');
        $other['dish']->update(['is_available' => false]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $other['dish']->id,
            'available_date' => $tomorrow,
        ]);

        $updated = $this->repository()->enableDishesIsAvailableForCategoryDates([
            (int) $catA['category']->id => $tomorrow,
            (int) $catB['category']->id => $dayAfter,
        ]);

        $this->assertGreaterThan(0, $updated);
        $this->assertTrue($catA['dish']->fresh()->is_available);
        $this->assertTrue($catB['dish']->fresh()->is_available);
        $this->assertFalse($other['dish']->fresh()->is_available);
    }
}
