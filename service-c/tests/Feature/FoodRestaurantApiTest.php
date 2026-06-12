<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\TestCase;

class FoodRestaurantApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;

    public function test_restaurants_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/food/restaurants')
            ->assertUnauthorized();
    }

    public function test_restaurants_endpoint_returns_only_active_restaurants(): void
    {
        $auth = $this->authenticateMaxUser();

        $active = Restaurant::factory()->create(['name' => 'Active Place']);
        Restaurant::factory()->inactive()->create(['name' => 'Closed Place']);

        $response = $this->getJson('/api/food/restaurants', $auth['headers']);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'restaurants')
            ->assertJsonPath('restaurants.0.id', $active->id)
            ->assertJsonPath('restaurants.0.name', 'Active Place');
    }

    public function test_menu_endpoint_returns_categories_and_dishes(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $response = $this->getJson(
            '/api/food/restaurants/'.$fixture['restaurant']->id.'/menu',
            $auth['headers'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('menu.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('menu.restaurant_name', $fixture['restaurant']->name)
            ->assertJsonPath('menu.categories.0.name', 'Main')
            ->assertJsonPath('menu.categories.0.dishes.0.id', $fixture['dish']->id)
            ->assertJsonPath('menu.categories.0.dishes.0.name', 'Test Pasta')
            ->assertJsonPath('menu.categories.0.dishes.0.price', '250.00')
            ->assertJsonPath('menu.categories.0.dishes.0.image_url', $fixture['dish']->image_url);
    }

    public function test_menu_endpoint_returns_not_found_for_inactive_restaurant(): void
    {
        $auth = $this->authenticateMaxUser();
        $restaurant = Restaurant::factory()->inactive()->create();

        $this->getJson('/api/food/restaurants/'.$restaurant->id.'/menu', $auth['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_menu_endpoint_returns_not_found_for_unknown_restaurant(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/restaurants/99999/menu', $auth['headers'])
            ->assertNotFound();
    }

    public function test_menu_endpoint_sorts_categories_by_sort_order(): void
    {
        $auth = $this->authenticateMaxUser();
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

        $response = $this->getJson('/api/food/restaurants/'.$restaurant->id.'/menu', $auth['headers']);

        $response
            ->assertOk()
            ->assertJsonPath('menu.categories.0.name', 'Starters')
            ->assertJsonPath('menu.categories.1.name', 'Desserts');
    }
}
