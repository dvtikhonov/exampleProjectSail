<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CartItem;
use App\Models\Dish;
use App\Models\MenuCategory;
use App\Services\Food\OrderItemsSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class OrderItemsSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;
    use ResolvesDishImageUrl;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Build создаёт снимок с форматированными суммами и URL изображения. */
    public function test_build_creates_snapshot_with_formatted_amounts_and_image_url(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Steak', 700.0);

        $item = new CartItem([
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ]);
        $item->setRelation('dish', $fixture['dish']);

        $snapshot = app(OrderItemsSnapshotBuilder::class)->build(collect([$item]));

        $this->assertSame(1400.0, $snapshot->itemsTotal);
        $this->assertCount(1, $snapshot->itemsSnapshot);
        $this->assertSame($fixture['dish']->id, $snapshot->itemsSnapshot[0]['dish_id']);
        $this->assertSame('Steak', $snapshot->itemsSnapshot[0]['dish_name']);
        $this->assertSame('700.00', $snapshot->itemsSnapshot[0]['unit_price']);
        $this->assertSame(2, $snapshot->itemsSnapshot[0]['quantity']);
        $this->assertSame('1400.00', $snapshot->itemsSnapshot[0]['line_total']);
        $this->assertArrayHasKey('description', $snapshot->itemsSnapshot[0]);
        $this->assertArrayHasKey('weight', $snapshot->itemsSnapshot[0]);
        $this->assertArrayHasKey('weight_unit', $snapshot->itemsSnapshot[0]);
        $this->assertSame(
            $this->expectedDishImageUrlForModel($fixture['dish']),
            $snapshot->itemsSnapshot[0]['image_url'],
        );
    }

    /** buildFromDishes строит снимок из Dish без зависимости от CartItem. */
    public function test_build_from_dishes_creates_snapshot_from_catalog_dishes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Soup', 320.0);

        $snapshot = app(OrderItemsSnapshotBuilder::class)->buildFromDishes([
            [
                'dish' => $fixture['dish'],
                'quantity' => 3,
            ],
        ]);

        $this->assertSame(960.0, $snapshot->itemsTotal);
        $this->assertCount(1, $snapshot->itemsSnapshot);
        $this->assertSame($fixture['dish']->id, $snapshot->itemsSnapshot[0]['dish_id']);
        $this->assertSame('Soup', $snapshot->itemsSnapshot[0]['dish_name']);
        $this->assertSame('320.00', $snapshot->itemsSnapshot[0]['unit_price']);
        $this->assertSame(3, $snapshot->itemsSnapshot[0]['quantity']);
        $this->assertSame('960.00', $snapshot->itemsSnapshot[0]['line_total']);
        $this->assertArrayNotHasKey('combo_ref', $snapshot->itemsSnapshot[0]);
        $this->assertSame(
            $this->expectedDishImageUrlForModel($fixture['dish']),
            $snapshot->itemsSnapshot[0]['image_url'],
        );
    }

    /** buildFromDishes сохраняет метаданные комбо в snapshot. */
    public function test_build_from_dishes_includes_combo_metadata(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Main', 400.0);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Side',
            'price' => 100.0,
        ]);
        $comboRef = (string) Str::uuid();

        $snapshot = app(OrderItemsSnapshotBuilder::class)->buildFromDishes([
            [
                'dish' => $fixture['dish'],
                'quantity' => 2,
                'combo_ref' => $comboRef,
                'combo_partner_dish_id' => $sideDish->id,
            ],
            [
                'dish' => $sideDish,
                'quantity' => 2,
                'combo_ref' => $comboRef,
                'combo_partner_dish_id' => $fixture['dish']->id,
            ],
        ]);

        $this->assertSame(1000.0, $snapshot->itemsTotal);
        $this->assertSame($comboRef, $snapshot->itemsSnapshot[0]['combo_ref']);
        $this->assertSame([$sideDish->id], $snapshot->itemsSnapshot[0]['combo_partner_dish_ids']);
        $this->assertSame('800.00', $snapshot->itemsSnapshot[0]['line_total']);
        $this->assertSame('200.00', $snapshot->itemsSnapshot[1]['line_total']);
    }
}
