<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
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

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Эндпоинты категорий меню возвращают 401 без токена. */
    public function test_menu_category_endpoints_return_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/admin/menu-categories')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /** Эндпоинты категорий меню возвращают 403 без роли менеджера меню. */
    public function test_menu_category_endpoints_return_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/menu-categories', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** Менеджер меню может получить список категорий меню. */
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

    /** Менеджер меню может фильтровать категории по restaurant_id. */
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

        $this->getJson(
            '/api/food/admin/menu-categories?restaurant_id='.$second['restaurant']->id,
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.id', $second['category']->id);
    }

    /** Список категорий отклоняет невалидный restaurant_id. */
    public function test_menu_categories_index_rejects_invalid_restaurant_id(): void
    {
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/menu-categories?restaurant_id=0', $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** Менеджер меню может создать категорию меню. */
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

    /** Менеджер меню может показать категорию меню. */
    public function test_menu_manager_can_show_menu_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/menu-categories/'.$fixture['category']->id, $auth['headers'])
            ->assertOk()
            ->assertJsonPath('category.id', $fixture['category']->id)
            ->assertJsonPath('category.name', 'Main');
    }

    /** Менеджер меню может обновить категорию меню. */
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

    /** Store отклоняет несуществующий ресторан. */
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

    /** Delete отклоняет категорию, у которой есть блюда. */
    public function test_delete_rejects_category_with_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->deleteJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [], $auth['headers'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Нельзя удалить категорию: в ней есть блюда.');
    }

    /** Менеджер меню может удалить пустую категорию меню. */
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

    /** Update отклоняет смену ресторана, если у категории есть блюда. */
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

    /** Эндпоинт меню включает флаг доступности комбо. */
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

    /** Менеджер меню может создать категорию с несколькими правилами смещения. */
    public function test_menu_manager_can_create_category_with_availability_offsets(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $response = $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Супы',
            'is_combo_available' => true,
            'availability_offsets' => [
                ['weekdays' => [1, 2, 3, 4], 'offset_days' => 2],
                ['weekdays' => [5], 'offset_days' => 3],
            ],
        ], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Супы');

        $offsets = $response->json('category.availability_offsets');
        $this->assertIsArray($offsets);
        $this->assertCount(2, $offsets);
        $this->assertContains(['weekdays' => [1, 2, 3, 4], 'offset_days' => 2], $offsets);
        $this->assertContains(['weekdays' => [5], 'offset_days' => 3], $offsets);

        $categoryId = (int) $response->json('category.id');
        $this->assertDatabaseCount('max_menu_category_availability_offsets', 5);
        $this->assertDatabaseHas('max_menu_category_availability_offsets', [
            'menu_category_id' => $categoryId,
            'weekday' => 1,
            'offset_days' => 2,
        ]);
        $this->assertDatabaseHas('max_menu_category_availability_offsets', [
            'menu_category_id' => $categoryId,
            'weekday' => 5,
            'offset_days' => 3,
        ]);
    }

    /** Менеджер меню может обновить категорию с несколькими правилами смещения. */
    public function test_menu_manager_can_update_category_with_availability_offsets(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->putJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => $fixture['category']->name,
            'sort_order' => $fixture['category']->sort_order,
            'is_combo_available' => true,
            'availability_offsets' => [
                ['weekdays' => [6, 7], 'offset_days' => 1],
                ['weekdays' => [1], 'offset_days' => 0],
            ],
        ], $auth['headers'])
            ->assertOk();

        $offsets = $response->json('category.availability_offsets');
        $this->assertIsArray($offsets);
        $this->assertCount(2, $offsets);
        $this->assertContains(['weekdays' => [6, 7], 'offset_days' => 1], $offsets);
        $this->assertContains(['weekdays' => [1], 'offset_days' => 0], $offsets);

        $this->assertDatabaseCount('max_menu_category_availability_offsets', 3);
        $this->assertDatabaseHas('max_menu_category_availability_offsets', [
            'menu_category_id' => $fixture['category']->id,
            'weekday' => 6,
            'offset_days' => 1,
        ]);
        $this->assertDatabaseHas('max_menu_category_availability_offsets', [
            'menu_category_id' => $fixture['category']->id,
            'weekday' => 1,
            'offset_days' => 0,
        ]);
    }

    /** Update полностью заменяет набор правил смещения, в том числе на пустой массив. */
    public function test_update_replaces_availability_offsets_including_empty_array(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $createResponse = $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Гарниры',
            'availability_offsets' => [
                ['weekdays' => [1, 2], 'offset_days' => 4],
                ['weekdays' => [3], 'offset_days' => 1],
            ],
        ], $auth['headers'])
            ->assertCreated();

        $categoryId = (int) $createResponse->json('category.id');
        $this->assertDatabaseCount('max_menu_category_availability_offsets', 3);

        $replaceResponse = $this->putJson('/api/food/admin/menu-categories/'.$categoryId, [
            'restaurant_id' => $restaurant->id,
            'name' => 'Гарниры',
            'sort_order' => 1,
            'is_combo_available' => true,
            'availability_offsets' => [
                ['weekdays' => [5, 6], 'offset_days' => 2],
            ],
        ], $auth['headers'])
            ->assertOk();

        $replacedOffsets = $replaceResponse->json('category.availability_offsets');
        $this->assertSame([['weekdays' => [5, 6], 'offset_days' => 2]], $replacedOffsets);
        $this->assertDatabaseCount('max_menu_category_availability_offsets', 2);
        $this->assertDatabaseMissing('max_menu_category_availability_offsets', [
            'menu_category_id' => $categoryId,
            'weekday' => 1,
        ]);

        $this->putJson('/api/food/admin/menu-categories/'.$categoryId, [
            'restaurant_id' => $restaurant->id,
            'name' => 'Гарниры',
            'sort_order' => 1,
            'is_combo_available' => true,
            'availability_offsets' => [],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('category.availability_offsets', []);

        $this->assertDatabaseCount('max_menu_category_availability_offsets', 0);
    }

    /** Store отклоняет пересечение дней недели между правилами смещения. */
    public function test_store_rejects_overlapping_availability_offset_weekdays(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Супы',
            'availability_offsets' => [
                ['weekdays' => [1, 2, 3], 'offset_days' => 2],
                ['weekdays' => [3, 4], 'offset_days' => 1],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability_offsets.1.weekdays']);

        $this->assertDatabaseCount('max_menu_category_availability_offsets', 0);
    }

    /** Update отклоняет пересечение дней недели между правилами смещения. */
    public function test_update_rejects_overlapping_availability_offset_weekdays(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->putJson('/api/food/admin/menu-categories/'.$fixture['category']->id, [
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => $fixture['category']->name,
            'sort_order' => $fixture['category']->sort_order,
            'is_combo_available' => true,
            'availability_offsets' => [
                ['weekdays' => [1, 5], 'offset_days' => 2],
                ['weekdays' => [5], 'offset_days' => 0],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability_offsets.1.weekdays']);
    }

    /** Store отклоняет день недели вне диапазона 1–7. */
    public function test_store_rejects_weekday_outside_iso_range(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Супы',
            'availability_offsets' => [
                ['weekdays' => [0], 'offset_days' => 1],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability_offsets.0.weekdays.0']);

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Супы',
            'availability_offsets' => [
                ['weekdays' => [8], 'offset_days' => 1],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability_offsets.0.weekdays.0']);
    }

    /** Store отклоняет пустой список дней недели в правиле смещения. */
    public function test_store_rejects_empty_weekdays_in_availability_offset(): void
    {
        $restaurant = Restaurant::factory()->create();
        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/menu-categories', [
            'restaurant_id' => $restaurant->id,
            'name' => 'Супы',
            'availability_offsets' => [
                ['weekdays' => [], 'offset_days' => 2],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['availability_offsets.0.weekdays']);
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
