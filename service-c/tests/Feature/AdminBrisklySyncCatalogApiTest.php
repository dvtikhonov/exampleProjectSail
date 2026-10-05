<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Admin proxy: GET briskly-sync/restaurants и /vps-categories (через VPS catalog port).
 */
class AdminBrisklySyncCatalogApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string RESTAURANTS = '/api/food/admin/briskly-sync/restaurants';

    private const string VPS_CATEGORIES = '/api/food/admin/briskly-sync/vps-categories';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        // Явно local path: Feature-тесты каталога работают с БД, не с remote PhotoText.
        config([
            'briskly_sync.source_remote' => false,
            'briskly_sync.source_base_url' => '',
        ]);

        $this->resetFoodDomainTables();
    }

    /** Без auth → 401. */
    public function test_catalog_endpoints_require_authentication(): void
    {
        $this->getJson(self::RESTAURANTS)->assertUnauthorized();
        $this->getJson(self::VPS_CATEGORIES.'?restaurant_id=1')->assertUnauthorized();
    }

    /** Без роли max_manager → 403. */
    public function test_catalog_endpoints_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson(self::RESTAURANTS, $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');

        $menuManager = $this->asFoodOrderAdmin($auth, FoodOrderAdminRole::MenuManager);

        $this->getJson(self::VPS_CATEGORIES.'?restaurant_id=1', $menuManager['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** max_manager получает активные рестораны через порт. */
    public function test_max_manager_can_list_restaurants(): void
    {
        $manager = $this->maxManagerAuth(31_001);
        $active = Restaurant::factory()->create([
            'name' => 'Столовая №1',
            'is_active' => true,
        ]);
        Restaurant::factory()->inactive()->create(['name' => 'Закрыто']);

        $this->getJson(self::RESTAURANTS, $manager['headers'])
            ->assertOk()
            ->assertJsonPath('restaurants.0.id', $active->id)
            ->assertJsonPath('restaurants.0.name', 'Столовая №1')
            ->assertJsonMissing(['name' => 'Закрыто']);
    }

    /** max_manager получает категории выбранного ресторана. */
    public function test_max_manager_can_list_vps_categories(): void
    {
        $manager = $this->maxManagerAuth(31_002);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $other = Restaurant::factory()->create(['is_active' => true]);
        $salads = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Салаты',
        ]);
        MenuCategory::factory()->create([
            'restaurant_id' => $other->id,
            'name' => 'Чужая категория',
        ]);

        $response = $this->getJson(
            self::VPS_CATEGORIES.'?restaurant_id='.$restaurant->id,
            $manager['headers'],
        )->assertOk();

        $categories = $response->json('categories');
        $this->assertIsArray($categories);
        $this->assertCount(1, $categories);
        $this->assertSame($salads->id, $categories[0]['id']);
        $this->assertSame('Салаты', $categories[0]['name']);
    }

    /** Без restaurant_id → 422 validation. */
    public function test_vps_categories_require_restaurant_id(): void
    {
        $manager = $this->maxManagerAuth(31_003);

        $this->getJson(self::VPS_CATEGORIES, $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** Неактивный / отсутствующий restaurant_id → 422 (порт). */
    public function test_vps_categories_reject_inactive_or_missing_restaurant(): void
    {
        $manager = $this->maxManagerAuth(31_004);
        $inactive = Restaurant::factory()->inactive()->create();

        $this->getJson(
            self::VPS_CATEGORIES.'?restaurant_id='.$inactive->id,
            $manager['headers'],
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ресторан не найден или неактивен.');

        $this->getJson(
            self::VPS_CATEGORIES.'?restaurant_id=999999',
            $manager['headers'],
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ресторан не найден или неактивен.');
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
