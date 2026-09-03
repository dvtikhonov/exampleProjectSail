<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Repositories\Food\Shared\EloquentRestaurantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentRestaurantRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** findAllActive возвращает только активные рестораны, отсортированные по имени. */
    public function test_find_all_active_returns_only_active_restaurants_sorted_by_name(): void
    {
        Restaurant::factory()->create(['name' => 'Zeta']);
        $beta = Restaurant::factory()->create(['name' => 'Beta']);
        Restaurant::factory()->inactive()->create(['name' => 'Alpha']);

        $repository = app(EloquentRestaurantRepository::class);

        $restaurants = $repository->findAllActive();

        $this->assertCount(2, $restaurants);
        $this->assertSame($beta->id, $restaurants[0]->id);
        $this->assertSame('Beta', $restaurants[0]->name);
        $this->assertSame('Zeta', $restaurants[1]->name);
    }

    /** findActiveWithMenu возвращает ресторан с категориями и блюдами. */
    public function test_find_active_with_menu_returns_restaurant_with_categories_and_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $repository = app(EloquentRestaurantRepository::class);

        $restaurant = $repository->findActiveWithMenu($fixture['restaurant']->id);

        $this->assertNotNull($restaurant);
        $this->assertSame($fixture['restaurant']->id, $restaurant->id);
        $this->assertCount(1, $restaurant->menuCategories);
        $this->assertSame('Main', $restaurant->menuCategories[0]->name);
        $this->assertCount(1, $restaurant->menuCategories[0]->dishes);
        $this->assertSame('Test Pasta', $restaurant->menuCategories[0]->dishes[0]->name);
    }

    /** findActiveWithMenu возвращает null для неактивного ресторана. */
    public function test_find_active_with_menu_returns_null_for_inactive_restaurant(): void
    {
        $restaurant = Restaurant::factory()->inactive()->create();

        $repository = app(EloquentRestaurantRepository::class);

        $this->assertNull($repository->findActiveWithMenu($restaurant->id));
    }

    /** findActiveWithMenu сортирует категории по sort_order. */
    public function test_find_active_with_menu_sorts_categories_by_sort_order(): void
    {
        $restaurant = Restaurant::factory()->create();

        MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Desserts',
            'sort_order' => 2,
        ]);

        MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Starters',
            'sort_order' => 1,
        ]);

        $repository = app(EloquentRestaurantRepository::class);

        $loaded = $repository->findActiveWithMenu($restaurant->id);

        $this->assertNotNull($loaded);
        $this->assertSame('Starters', $loaded->menuCategories[0]->name);
        $this->assertSame('Desserts', $loaded->menuCategories[array_key_last($loaded->menuCategories)]->name);
    }

    /** findActiveWithMenu с includeUnavailable включает недоступные блюда. */
    public function test_find_active_with_menu_can_include_unavailable_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['is_available' => false]);

        $repository = app(EloquentRestaurantRepository::class);

        $withoutUnavailable = $repository->findActiveWithMenu($fixture['restaurant']->id);
        $withUnavailable = $repository->findActiveWithMenu($fixture['restaurant']->id, true);

        $this->assertNotNull($withoutUnavailable);
        $this->assertCount(0, $withoutUnavailable->menuCategories[0]->dishes);

        $this->assertNotNull($withUnavailable);
        $this->assertCount(1, $withUnavailable->menuCategories[0]->dishes);
        $this->assertFalse($withUnavailable->menuCategories[0]->dishes[0]->isAvailable);
    }
}
