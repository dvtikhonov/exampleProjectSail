<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DishAvailabilityDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class SyncDishAvailabilityCommandTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_sets_is_available_true_when_today_is_in_schedule(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 10:00:00', 'Europe/Moscow'));
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Europe/Moscow'));

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $dish = $fixture['dish'];
        $dish->update(['is_available' => false]);

        DishAvailabilityDate::query()->create([
            'dish_id' => $dish->id,
            'available_date' => '2026-07-10',
        ]);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
        $this->assertTrue($dish->fresh()->is_available);
        $this->assertStringContainsString('обновлено 1', Artisan::output());
    }

    public function test_command_sets_is_available_false_when_today_not_in_schedule(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 10:00:00', 'Europe/Moscow'));
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Europe/Moscow'));

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $dish = $fixture['dish'];
        $dish->update(['is_available' => true]);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
        $this->assertFalse($dish->fresh()->is_available);
        $this->assertStringContainsString('обновлено 1', Artisan::output());
    }

    public function test_yesterday_schedule_does_not_make_dish_available_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 10:00:00', 'Europe/Moscow'));
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', 'Europe/Moscow'));

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $dish = $fixture['dish'];
        $dish->update(['is_available' => true]);

        DishAvailabilityDate::query()->create([
            'dish_id' => $dish->id,
            'available_date' => '2026-07-09',
        ]);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
        $this->assertFalse($dish->fresh()->is_available);
    }
}
