<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\DailyMenuLineType;
use App\Enums\Food\DishWeightUnit;
use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Services\Food\DailyMenuLineCollector;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class DailyMenuLineCollectorTest extends TestCase
{
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Collector оставляет одиночные блюда из категорий без комбо. */
    public function test_collects_standalone_dishes_from_non_combo_categories(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Салаты',
            'sort_order' => 1,
            'is_combo_available' => false,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Салат Цезарь',
            'description' => 'курица, сыр',
            'weight' => 110,
            'weight_unit' => DishWeightUnit::Gram,
            'price' => 97,
            'is_available' => true,
        ]);
        Dish::factory()->unavailable()->create([
            'menu_category_id' => $category->id,
            'name' => 'Недоступный',
        ]);

        $lines = $this->app->make(DailyMenuLineCollector::class)->collect();

        $this->assertCount(1, $lines);
        $this->assertSame(DailyMenuLineType::Single, $lines[0]->type);
        $this->assertSame('Салат Цезарь', $lines[0]->parts[0]->name);
        $this->assertSame('курица, сыр', $lines[0]->parts[0]->description);
        $this->assertSame('110г', $lines[0]->parts[0]->weightLabel);
        $this->assertSame(97.0, $lines[0]->parts[0]->price);
    }

    /** Collector строит комбо-пары из разных категорий с is_combo_available. */
    public function test_collects_combo_pairs_across_combo_categories(): void
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
            'weight' => 130,
            'weight_unit' => DishWeightUnit::Gram,
            'price' => 120,
            'is_available' => true,
        ]);
        $chicken = Dish::factory()->create([
            'menu_category_id' => $mains->id,
            'name' => 'Куриное филе',
            'weight' => 120,
            'weight_unit' => DishWeightUnit::Gram,
            'price' => 100,
            'is_available' => true,
        ]);
        $pasta = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Макароны',
            'weight' => 150,
            'weight_unit' => DishWeightUnit::Gram,
            'price' => 82,
            'is_available' => true,
        ]);
        $potato = Dish::factory()->create([
            'menu_category_id' => $sides->id,
            'name' => 'Картофель',
            'weight' => 150,
            'weight_unit' => DishWeightUnit::Gram,
            'price' => 95,
            'is_available' => true,
        ]);

        $lines = $this->app->make(DailyMenuLineCollector::class)->collect();

        $this->assertCount(4, $lines);

        foreach ($lines as $line) {
            $this->assertSame(DailyMenuLineType::Combo, $line->type);
            $this->assertCount(2, $line->parts);
            $this->assertSame(1, $line->quantity);
        }

        $labels = array_map(
            static fn ($line): string => $line->parts[0]->name.' / '.$line->parts[1]->name,
            $lines,
        );

        $this->assertSame([
            $fish->name.' / '.$pasta->name,
            $fish->name.' / '.$potato->name,
            $chicken->name.' / '.$pasta->name,
            $chicken->name.' / '.$potato->name,
        ], $labels);
    }

    /** Одна комбо-категория без пары превращается в одиночные позиции. */
    public function test_falls_back_to_single_when_only_one_combo_category(): void
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => true,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Одинокое комбо-блюдо',
            'is_available' => true,
        ]);

        $lines = $this->app->make(DailyMenuLineCollector::class)->collect();

        $this->assertCount(1, $lines);
        $this->assertSame(DailyMenuLineType::Single, $lines[0]->type);
        $this->assertSame('Одинокое комбо-блюдо', $lines[0]->parts[0]->name);
    }
}
