<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\Menu\DailyMenuLineType;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Services\Food\BrisklySync\BrisklySyncSourceCollector;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class BrisklySyncSourceCollectorTest extends TestCase
{
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Happy path: одиночные блюда из не-комбо категории (в т.ч. is_available=false). */
    public function test_collects_single_lines_for_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Салат Цезарь',
            'price' => 97.5,
            'weight' => 180,
            'weight_unit' => DishWeightUnit::Gram,
            'is_available' => true,
        ]);
        $unavailable = Dish::factory()->unavailable()->create([
            'menu_category_id' => $category->id,
            'name' => 'Недоступный',
            'price' => 65,
            'weight' => null,
            'weight_unit' => DishWeightUnit::Gram,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertCount(2, $lines);
        $this->assertSame(DailyMenuLineType::Single, $lines[0]->type);
        $this->assertSame('single:'.$dish->id, $lines[0]->lineKey);
        $this->assertSame('Салат Цезарь', $lines[0]->displayName);
        $this->assertSame('Салат Цезарь, 180г', $lines[0]->brisklyCreateName);
        $this->assertSame('97.50', $lines[0]->price);
        $this->assertSame([$dish->id], $lines[0]->partDishIds);
        $this->assertSame('single:'.$unavailable->id, $lines[1]->lineKey);
        $this->assertSame('Недоступный', $lines[1]->displayName);
        $this->assertSame('Недоступный', $lines[1]->brisklyCreateName);
        $this->assertSame('65.00', $lines[1]->price);
    }

    /** Combo: displayName без веса, brisklyCreateName с весами частей. */
    public function test_combo_briskly_create_name_includes_part_weights(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $mains = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'sort_order' => 1,
            'is_combo_available' => true,
        ]);
        $sides = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'sort_order' => 2,
            'is_combo_available' => true,
        ]);
        $soup = Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'Суп',
            'price' => 100,
            'weight' => 300,
            'weight_unit' => DishWeightUnit::Gram,
            'is_available' => true,
        ]);
        $salad = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Салат',
            'price' => 80,
            'weight' => 150,
            'weight_unit' => DishWeightUnit::Gram,
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertCount(1, $lines);
        $this->assertSame('Суп / Салат', $lines[0]->displayName);
        $this->assertSame('Суп, 300г / Салат, 150г', $lines[0]->brisklyCreateName);
        $this->assertSame([$soup->id, $salad->id], $lines[0]->partDishIds);
    }

    /** Комбо: unordered-декартово, сумма цен, имя A / B, без дубля B/A. */
    public function test_collects_combo_pairs_with_summed_price_and_stable_keys(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $mains = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Горячее',
            'sort_order' => 1,
            'is_combo_available' => true,
        ]);
        $sides = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
            'is_combo_available' => true,
        ]);
        $fish = Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'Филе минтая',
            'price' => 120,
            'is_available' => true,
        ]);
        $chicken = Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'Куриное филе',
            'price' => 100,
            'is_available' => true,
        ]);
        $pasta = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Макароны',
            'price' => 82,
            'is_available' => true,
        ]);
        $potato = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Картофель',
            'price' => 95,
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertCount(4, $lines);

        foreach ($lines as $line) {
            $this->assertSame(DailyMenuLineType::Combo, $line->type);
            $this->assertCount(2, $line->partDishIds);
            $this->assertStringStartsWith('combo:', $line->lineKey);
        }

        $labels = array_map(static fn ($line): string => $line->displayName, $lines);
        $this->assertSame([
            $fish->name.' / '.$pasta->name,
            $fish->name.' / '.$potato->name,
            $chicken->name.' / '.$pasta->name,
            $chicken->name.' / '.$potato->name,
        ], $labels);

        $keys = array_map(static fn ($line): string => $line->lineKey, $lines);
        $this->assertSame($keys, array_values(array_unique($keys)));

        $reversed = $pasta->name.' / '.$fish->name;
        $this->assertNotContains($reversed, $labels);

        $this->assertSame('202.00', $lines[0]->price);
        $this->assertSame([$fish->id, $pasta->id], $lines[0]->partDishIds);
        $this->assertSame('combo:'.$fish->id.':'.$pasta->id, $lines[0]->lineKey);
    }

    /** Одна combo-категория → fallback в single. */
    public function test_falls_back_to_single_when_only_one_combo_category(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => true,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Одинокое комбо-блюдо',
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertCount(1, $lines);
        $this->assertSame(DailyMenuLineType::Single, $lines[0]->type);
        $this->assertSame('single:'.$dish->id, $lines[0]->lineKey);
        $this->assertSame('Одинокое комбо-блюдо', $lines[0]->displayName);
    }

    /** Недоступные блюда включаются в source (сравнение цен с Briskly вне меню дня). */
    public function test_includes_unavailable_dishes_for_price_sync(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->unavailable()->create([
            'menu_category_id' => $category->id,
            'name' => 'Сельдь под шубой',
            'price' => 65,
        ]);

        $lines = $this->collector()->collectForRestaurant(
            $restaurant->id,
            $category->id,
            'Сельдь под шубой',
        );

        $this->assertCount(1, $lines);
        $this->assertSame('single:'.$dish->id, $lines[0]->lineKey);
        $this->assertSame('Сельдь под шубой', $lines[0]->displayName);
        $this->assertSame('65.00', $lines[0]->price);
    }

    /** Нет блюд в ресторане → пустой список без ошибки. */
    public function test_returns_empty_list_when_no_dishes(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertSame([], $lines);
    }

    /** Неактивный ресторан → 422. */
    public function test_throws_when_restaurant_inactive(): void
    {
        $restaurant = Restaurant::factory()->inactive()->create();

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Ресторан не найден или неактивен.');

        $this->collector()->collectForRestaurant($restaurant->id);
    }

    /** Несуществующий ресторан → 422. */
    public function test_throws_when_restaurant_missing(): void
    {
        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Ресторан не найден или неактивен.');

        $this->collector()->collectForRestaurant(999_999);
    }

    /** Чужой vps_category_id → 422. */
    public function test_throws_when_vps_category_belongs_to_another_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $other = Restaurant::factory()->create(['is_active' => true]);
        $foreignCategory = MenuCategory::factory()->create([
            'restaurant_id' => $other->id,
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Категория меню не найдена для выбранного ресторана.');

        $this->collector()->collectForRestaurant($restaurant->id, $foreignCategory->id);
    }

    /** Чужой restaurant_id не отдаёт чужое меню. */
    public function test_does_not_return_other_restaurant_menu(): void
    {
        $target = Restaurant::factory()->create(['is_active' => true]);
        $other = Restaurant::factory()->create(['is_active' => true]);

        $targetCategory = MenuCategory::factory()->create([
            'restaurant_id' => $target->id,
            'is_combo_available' => false,
        ]);
        $otherCategory = MenuCategory::factory()->create([
            'restaurant_id' => $other->id,
            'is_combo_available' => false,
        ]);

        $ownDish = Dish::factory()->create([
            'menu_category_id' => $targetCategory->id,
            'name' => 'Своё блюдо',
            'is_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $otherCategory->id,
            'name' => 'Чужое блюдо',
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($target->id);

        $this->assertCount(1, $lines);
        $this->assertSame('Своё блюдо', $lines[0]->displayName);
        $this->assertSame([$ownDish->id], $lines[0]->partDishIds);
    }

    /** Фильтр vps_category_id оставляет линии с блюдом из категории (в т.ч. комбо). */
    public function test_filters_by_vps_category_id(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $salads = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Салаты',
            'sort_order' => 1,
            'is_combo_available' => false,
        ]);
        $mains = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Горячее',
            'sort_order' => 2,
            'is_combo_available' => true,
        ]);
        $sides = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Гарниры',
            'sort_order' => 3,
            'is_combo_available' => true,
        ]);

        Dish::factory()->create([
            'menu_category_id' => $salads->id,
            'name' => 'Цезарь',
            'is_available' => true,
        ]);
        $fish = Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'Рыба',
            'price' => 100,
            'is_available' => true,
        ]);
        $rice = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Рис',
            'price' => 50,
            'is_available' => true,
        ]);

        $saladLines = $this->collector()->collectForRestaurant($restaurant->id, $salads->id);
        $this->assertCount(1, $saladLines);
        $this->assertSame('Цезарь', $saladLines[0]->displayName);

        $mainLines = $this->collector()->collectForRestaurant($restaurant->id, $mains->id);
        $this->assertCount(1, $mainLines);
        $this->assertSame(DailyMenuLineType::Combo, $mainLines[0]->type);
        $this->assertSame($fish->name.' / '.$rice->name, $mainLines[0]->displayName);
    }

    /** Фильтр search_text — подстрока в display_name без учёта регистра. */
    public function test_filters_by_search_text_case_insensitive(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Куриное филе',
            'is_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Салат Цезарь',
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id, null, 'куриное');

        $this->assertCount(1, $lines);
        $this->assertSame('Куриное филе', $lines[0]->displayName);
    }

    /** Пустые/пробельные имена, цена 0.00, очень длинное имя — без падения. */
    public function test_handles_edge_case_names_and_zero_price(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        // varchar(255): длинное имя на границе колонки, без падения коллектора.
        $longName = str_repeat('Д', 255);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => '   ',
            'price' => 0,
            'weight' => 0,
            'weight_unit' => DishWeightUnit::Gram,
            'is_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => $longName,
            'price' => 0.00,
            'is_available' => true,
        ]);

        $lines = $this->collector()->collectForRestaurant($restaurant->id);

        $this->assertCount(2, $lines);
        $this->assertSame('', $lines[0]->displayName);
        $this->assertSame('0.00', $lines[0]->price);
        $this->assertSame($longName, $lines[1]->displayName);
        $this->assertSame('0.00', $lines[1]->price);
    }

    /** line_key стабилен между повторными вызовами. */
    public function test_line_keys_are_stable_across_calls(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $mains = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'sort_order' => 1,
            'is_combo_available' => true,
        ]);
        $sides = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'sort_order' => 2,
            'is_combo_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'A',
            'is_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'B',
            'is_available' => true,
        ]);

        $first = array_map(
            static fn ($line): string => $line->lineKey,
            $this->collector()->collectForRestaurant($restaurant->id),
        );
        $second = array_map(
            static fn ($line): string => $line->lineKey,
            $this->collector()->collectForRestaurant($restaurant->id),
        );

        $this->assertSame($first, $second);
    }

    private function collector(): BrisklySyncSourceCollector
    {
        return $this->app->make(BrisklySyncSourceCollector::class);
    }
}
