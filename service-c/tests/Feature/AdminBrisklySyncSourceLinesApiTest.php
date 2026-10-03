<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminBrisklySyncSourceLinesApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string ENDPOINT = '/api/food/admin/briskly-sync/source-lines';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Без auth → 401. */
    public function test_source_lines_requires_authentication(): void
    {
        $this->getJson(self::ENDPOINT.'?restaurant_id=1')
            ->assertUnauthorized();
    }

    /** Без роли max_manager → 403 (в т.ч. menu_manager). */
    public function test_source_lines_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson(self::ENDPOINT.'?restaurant_id=1', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');

        $menuManager = $this->asFoodOrderAdmin($auth, FoodOrderAdminRole::MenuManager);

        $this->getJson(self::ENDPOINT.'?restaurant_id=1', $menuManager['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** max_manager получает source-lines выбранного ресторана. */
    public function test_max_manager_can_list_source_lines(): void
    {
        $manager = $this->maxManagerAuth(30_001);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Борщ',
            'price' => 150,
            'is_available' => true,
        ]);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id,
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('source_lines.0.line_key', 'single:'.$dish->id)
            ->assertJsonPath('source_lines.0.type', 'single')
            ->assertJsonPath('source_lines.0.display_name', 'Борщ')
            ->assertJsonPath('source_lines.0.price', '150.00')
            ->assertJsonPath('source_lines.0.part_dish_ids', [$dish->id]);
    }

    /** Неактивный / отсутствующий restaurant_id → 422. */
    public function test_source_lines_rejects_inactive_or_missing_restaurant(): void
    {
        $manager = $this->maxManagerAuth(30_002);
        $inactive = Restaurant::factory()->inactive()->create();

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$inactive->id,
            $manager['headers'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id=999999',
            $manager['headers'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** vps_category_id чужого ресторана → 422. */
    public function test_source_lines_rejects_foreign_vps_category(): void
    {
        $manager = $this->maxManagerAuth(30_003);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $other = Restaurant::factory()->create(['is_active' => true]);
        $foreignCategory = MenuCategory::factory()->create([
            'restaurant_id' => $other->id,
        ]);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id.'&vps_category_id='.$foreignCategory->id,
            $manager['headers'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vps_category_id']);
    }

    /** Фильтры vps_category_id и search_text работают. */
    public function test_source_lines_applies_category_and_search_filters(): void
    {
        $manager = $this->maxManagerAuth(30_004);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $salads = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $soups = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $salads->id,
            'name' => 'Салат Оливье',
            'is_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $soups->id,
            'name' => 'Суп харчо',
            'is_available' => true,
        ]);

        $byCategory = $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id.'&vps_category_id='.$salads->id,
            $manager['headers'],
        )->assertOk();

        $this->assertCount(1, $byCategory->json('source_lines'));
        $this->assertSame('Салат Оливье', $byCategory->json('source_lines.0.display_name'));

        $bySearch = $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id.'&search_text=ХАРЧО',
            $manager['headers'],
        )->assertOk();

        $this->assertCount(1, $bySearch->json('source_lines'));
        $this->assertSame('Суп харчо', $bySearch->json('source_lines.0.display_name'));
    }

    /** Пустой каталог → пустой source_lines, не ошибка. */
    public function test_source_lines_empty_when_no_available_dishes(): void
    {
        $manager = $this->maxManagerAuth(30_005);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id,
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('source_lines', []);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
