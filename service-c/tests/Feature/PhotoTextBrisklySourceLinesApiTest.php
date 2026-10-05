<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ConfiguresPhotoTextAgent;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextBrisklySourceLinesApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use ConfiguresPhotoTextAgent;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string AGENT_TOKEN = 'phototext-briskly-source-token';

    private const string WRITE_TOKEN = 'phototext-briskly-write-token';

    private const string ENDPOINT = '/api/food/phototext/briskly-source-lines';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        config([
            'phototext.agent_token' => self::AGENT_TOKEN,
            'phototext.write_token' => self::WRITE_TOKEN,
        ]);
    }

    /** Без X-PhotoText-Token → 401. */
    public function test_source_lines_requires_agent_token(): void
    {
        $this->getJson(self::ENDPOINT.'?restaurant_id=1')
            ->assertUnauthorized();
    }

    /** Без активного AI-доступа → 403. */
    public function test_source_lines_forbidden_without_ai_access(): void
    {
        $this->getJson(self::ENDPOINT.'?restaurant_id=1', $this->photoTextHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ AI к базе не разрешён.');
    }

    /** Агент с AI-доступом получает source-lines выбранного ресторана. */
    public function test_agent_with_ai_access_can_list_source_lines(): void
    {
        $manager = $this->phototextManager(40_001, 'BrisklyPhotoTextManager');
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

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
            $this->photoTextHeaders(),
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
        $manager = $this->phototextManager(40_002, 'BrisklyPhotoTextManager');
        $this->configurePhotoTextAgent($manager['user']->max_user_id);
        $inactive = Restaurant::factory()->inactive()->create();

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$inactive->id,
            $this->photoTextHeaders(),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id=999999',
            $this->photoTextHeaders(),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['restaurant_id']);
    }

    /** vps_category_id чужого ресторана → 422. */
    public function test_source_lines_rejects_foreign_vps_category(): void
    {
        $manager = $this->phototextManager(40_003, 'BrisklyPhotoTextManager');
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $other = Restaurant::factory()->create(['is_active' => true]);
        $foreignCategory = MenuCategory::factory()->create([
            'restaurant_id' => $other->id,
        ]);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id.'&vps_category_id='.$foreignCategory->id,
            $this->photoTextHeaders(),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vps_category_id']);
    }

    /** Фильтры vps_category_id и search_text работают. */
    public function test_source_lines_applies_category_and_search_filters(): void
    {
        $manager = $this->phototextManager(40_004, 'BrisklyPhotoTextManager');
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

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
            $this->photoTextHeaders(),
        )->assertOk();

        $this->assertCount(1, $byCategory->json('source_lines'));
        $this->assertSame('Салат Оливье', $byCategory->json('source_lines.0.display_name'));

        $bySearch = $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id.'&search_text=ХАРЧО',
            $this->photoTextHeaders(),
        )->assertOk();

        $this->assertCount(1, $bySearch->json('source_lines'));
        $this->assertSame('Суп харчо', $bySearch->json('source_lines.0.display_name'));
    }

    /** Пустой каталог → пустой source_lines, не ошибка. */
    public function test_source_lines_empty_when_no_available_dishes(): void
    {
        $manager = $this->phototextManager(40_005, 'BrisklyPhotoTextManager');
        $this->configurePhotoTextAgent($manager['user']->max_user_id);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->getJson(
            self::ENDPOINT.'?restaurant_id='.$restaurant->id,
            $this->photoTextHeaders(),
        )
            ->assertOk()
            ->assertJsonPath('source_lines', []);
    }
}
