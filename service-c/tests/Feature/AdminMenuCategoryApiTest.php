<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminMenuCategoryApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_menu_category_endpoints_return_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/admin/menu-categories')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_menu_category_endpoints_return_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/menu-categories', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_menu_manager_can_list_menu_categories(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['category']->update([
            'is_combo_available' => false,
            'sort_order' => 3,
        ]);

        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/menu-categories', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.id', $fixture['category']->id)
            ->assertJsonPath('categories.0.restaurant_name', $fixture['restaurant']->name)
            ->assertJsonPath('categories.0.sort_order', 3)
            ->assertJsonPath('categories.0.is_combo_available', false)
            ->assertJsonPath('categories.0.dishes_count', 1);
    }

    public function test_menu_manager_can_filter_categories_by_restaurant_id(): void
    {
        $first = FoodTestDataBuilder::createRestaurantWithDish('First', 'Soup');
        $second = FoodTestDataBuilder::createRestaurantWithDish('Second', 'Burger');

        $auth = $this->menuManagerAuth();

        $this->getJson(
            '/api/food/admin/menu-categories?restaurant_id='.$first['restaurant']->id,
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.id', $first['category']->id);
    }

    public function test_menu_manager_can_create_menu_category(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Десерты',
            'is_combo_available' => true,
        ], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Десерты')
            ->assertJsonPath('category.restaurant_id', $restaurant->id)
            ->assertJsonPath('category.is_combo_available', true)
            ->assertJsonPath('category.sort_order', 1)
            ->assertJsonPath('category.dishes_count', 0);

        $this->assertDatabaseHas('max_menu_categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Десерты',
            'is_combo_available' => true,
        ]);
    }

    public function test_menu_manager_can_show_menu_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/menu-categories/'.$fixture['category']->id, $auth['headers'])
            ->assertOk()
            ->assertJsonPath('category.id', $fixture['category']->id)
            ->assertJsonPath('category.name', 'Main');
    }

    public function test_menu_manager_can_update_menu_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->putJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Основные блюда',
            'sort_order' => 5,
            'is_combo_available' => false,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('category.name', 'Основные блюда')
            ->assertJsonPath('category.sort_order', 5)
            ->assertJsonPath('category.is_combo_available', false);

        $this->assertDatabaseHas('max_menu_categories', [
            'id' => $fixture['category']->id,
            'name' => 'Основные блюда',
            'sort_order' => 5,
            'is_combo_available' => false,
        ]);
    }

    public function test_store_rejects_nonexistent_restaurant(): void
    {
        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => 999_999,
            'name' => 'Десерты',
        ], $auth['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ресторан не найден.');
    }

    public function test_delete_rejects_category_with_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->deleteJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [], $auth['headers'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Нельзя удалить категорию: в ней есть блюда.');
    }

    public function test_menu_manager_can_delete_empty_menu_category(): void
    {
        $restaurant = Restaurant::factory()->create();
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);
        $auth = $this->menuManagerAuth();

        $this->deleteJson('/api/food/admin/menu-categories/'.$category->id, [], $auth['headers'])
            ->assertNoContent();

        $this->assertSoftDeleted('max_menu_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_update_rejects_restaurant_change_when_category_has_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $anotherRestaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $this->putJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [
            'restaurant_id' => $anotherRestaurant->id,
            'name' => $fixture['category']->name,
            'sort_order' => $fixture['category']->sort_order,
            'is_combo_available' => true,
        ], $auth['headers'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Нельзя сменить ресторан: в категории есть блюда.');
    }

    public function test_menu_endpoint_includes_combo_availability_flag(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['category']->update(['is_combo_available' => false]);
        $auth = $this->authenticateMaxUser();

        $this->getJson(
            '/api/food/restaurants/'.$fixture['restaurant']->id.'/menu',
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonPath('menu.categories.0.is_combo_available', false);
    }

    /**
     * @return array{user: MaxUser, token: string, headers: array<string, string>}
     */
    private function menuManagerAuth(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_011,
                'first_name' => 'CategoryManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }
}
