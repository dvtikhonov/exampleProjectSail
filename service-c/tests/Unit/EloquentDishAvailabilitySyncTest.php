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
}
