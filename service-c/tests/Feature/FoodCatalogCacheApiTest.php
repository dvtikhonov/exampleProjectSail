<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\DTO\Food\Menu\UpdateDishDto;
use App\Enums\Food\Menu\DishVatRate;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Models\Food\Dish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Кэш каталога: повторные GET и отсутствие stale после админ-мутаций.
 */
class FoodCatalogCacheApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetFoodDomainTables();
        config(['food.catalog_cache_enabled' => true]);
    }

    /** Повторный GET /restaurants отдаёт тот же JSON (hit кэша). */
    public function test_second_restaurants_request_returns_same_json(): void
    {
        $auth = $this->authenticateMaxUser();
        FoodTestDataBuilder::createRestaurantWithDish();

        $first = $this->getJson('/api/food/restaurants', $auth['headers'])->assertOk();
        $second = $this->getJson('/api/food/restaurants', $auth['headers'])->assertOk();

        $this->assertSame($first->json(), $second->json());
    }

    /** Повторный GET .../menu отдаёт тот же JSON (hit кэша). */
    public function test_second_menu_request_returns_same_json(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $url = '/api/food/restaurants/'.$fixture['restaurant']->id.'/menu';
        $first = $this->getJson($url, $auth['headers'])->assertOk();
        $second = $this->getJson($url, $auth['headers'])->assertOk();

        $this->assertSame($first->json(), $second->json());
    }

    /** После update через DishAdminService клиентское меню сразу отражает изменение. */
    public function test_menu_reflects_dish_admin_update_without_stale_cache(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(
            dishName: 'Old Pasta',
            price: 250.00,
        );
        $restaurantId = $fixture['restaurant']->id;
        $dish = $fixture['dish'];
        $menuUrl = '/api/food/restaurants/'.$restaurantId.'/menu';

        $this->getJson($menuUrl, $auth['headers'])
            ->assertOk()
            ->assertJsonPath('menu.categories.0.dishes.0.name', 'Old Pasta')
            ->assertJsonPath('menu.categories.0.dishes.0.price', '250.00');

        $this->app->make(DishAdminServiceInterface::class)->update(
            $dish->id,
            $this->updateDtoFromDish($dish, name: 'New Pasta', price: '399.00'),
        );

        $this->getJson($menuUrl, $auth['headers'])
            ->assertOk()
            ->assertJsonPath('menu.categories.0.dishes.0.name', 'New Pasta')
            ->assertJsonPath('menu.categories.0.dishes.0.price', '399.00');
    }

    /** После soft-delete через DishAdminService блюдо сразу исчезает из меню. */
    public function test_menu_reflects_dish_admin_delete_without_stale_cache(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'To Delete');
        $restaurantId = $fixture['restaurant']->id;
        $menuUrl = '/api/food/restaurants/'.$restaurantId.'/menu';

        $this->getJson($menuUrl, $auth['headers'])
            ->assertOk()
            ->assertJsonPath('menu.categories.0.dishes.0.name', 'To Delete');

        $this->app->make(DishAdminServiceInterface::class)->delete($fixture['dish']->id);

        $this->getJson($menuUrl, $auth['headers'])
            ->assertOk()
            ->assertJsonCount(0, 'menu.categories');
    }

    /**
     * Собирает UpdateDishDto на базе существующего блюда с переопределениями.
     */
    private function updateDtoFromDish(
        Dish $dish,
        ?string $name = null,
        ?string $price = null,
    ): UpdateDishDto {
        $weightUnit = $dish->weight_unit ?? DishWeightUnit::Gram;

        return new UpdateDishDto(
            name: $name ?? $dish->name,
            menuCategoryId: (int) $dish->menu_category_id,
            description: $dish->description,
            weight: (string) (int) round((float) $dish->weight),
            weightUnit: $weightUnit,
            price: $price ?? (string) $dish->price,
            vatRate: DishVatRate::fromValue($dish->vat_rate),
            isAvailable: (bool) $dish->is_available,
        );
    }
}
