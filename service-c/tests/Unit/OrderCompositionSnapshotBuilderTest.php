<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Food\FoodDomainException;
use App\Models\Dish;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Services\Food\OrderCompositionSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class OrderCompositionSnapshotBuilderTest extends TestCase
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

    /** Build собирает snapshot из dish_id и пересчитывает totals с доставкой. */
    public function test_build_creates_snapshot_and_recalculates_totals_with_delivery(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            dishName: 'Steak',
            price: 700.0,
            tiers: [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 200.00],
            ],
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']);

        $result = app(OrderCompositionSnapshotBuilder::class)->build(
            restaurantId: $fixture['restaurant']->id,
            customer: $customer,
            items: [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 2,
                    'combo_ref' => null,
                    'combo_partner_dish_id' => null,
                ],
            ],
        );

        $this->assertSame('1400.00', $result->itemsTotal);
        $this->assertSame('0.00', $result->deliveryCost);
        $this->assertSame('1400.00', $result->total);
        $this->assertCount(1, $result->itemsSnapshot);
        $this->assertSame($fixture['dish']->id, $result->itemsSnapshot[0]['dish_id']);
        $this->assertSame('Steak', $result->itemsSnapshot[0]['dish_name']);
        $this->assertSame('700.00', $result->itemsSnapshot[0]['unit_price']);
        $this->assertSame(2, $result->itemsSnapshot[0]['quantity']);
        $this->assertSame('1400.00', $result->itemsSnapshot[0]['line_total']);
        $this->assertSame(
            $this->expectedDishImageUrlForModel($fixture['dish']),
            $result->itemsSnapshot[0]['image_url'],
        );
    }

    /** Build берёт актуальные цены каталога и включает метаданные комбо. */
    public function test_build_uses_catalog_prices_and_combo_metadata(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Main', 500.0);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Side',
            'price' => 150.0,
        ]);
        $customer = $this->createCustomerWithoutDelivery();
        $comboRef = (string) Str::uuid();

        $result = app(OrderCompositionSnapshotBuilder::class)->build(
            restaurantId: $fixture['restaurant']->id,
            customer: $customer,
            items: [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $sideDish->id,
                ],
                [
                    'dish_id' => $sideDish->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $fixture['dish']->id,
                ],
            ],
        );

        $this->assertSame('650.00', $result->itemsTotal);
        $this->assertNull($result->deliveryCost);
        $this->assertSame('650.00', $result->total);
        $this->assertSame($comboRef, $result->itemsSnapshot[0]['combo_ref']);
        $this->assertSame([$sideDish->id], $result->itemsSnapshot[0]['combo_partner_dish_ids']);
        $this->assertSame($comboRef, $result->itemsSnapshot[1]['combo_ref']);
        $this->assertSame([$fixture['dish']->id], $result->itemsSnapshot[1]['combo_partner_dish_ids']);
    }

    /** Build отклоняет блюдо другого ресторана. */
    public function test_build_rejects_dish_from_another_restaurant(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('A', 'Dish A', 100.0);
        $other = FoodTestDataBuilder::createRestaurantWithDish('B', 'Dish B', 200.0);
        $customer = $this->createCustomerWithoutDelivery();

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Dish does not belong to the order restaurant.');

        app(OrderCompositionSnapshotBuilder::class)->build(
            restaurantId: $fixture['restaurant']->id,
            customer: $customer,
            items: [
                [
                    'dish_id' => $other['dish']->id,
                    'quantity' => 1,
                    'combo_ref' => null,
                    'combo_partner_dish_id' => null,
                ],
            ],
        );
    }

    /** Build отклоняет битую комбо-пару. */
    public function test_build_rejects_broken_combo_pair(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Bistro', 'Main', 500.0);
        $customer = $this->createCustomerWithoutDelivery();
        $comboRef = (string) Str::uuid();

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage(sprintf('Combo pair "%s" must contain exactly two items.', $comboRef));

        app(OrderCompositionSnapshotBuilder::class)->build(
            restaurantId: $fixture['restaurant']->id,
            customer: $customer,
            items: [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => 999,
                ],
            ],
        );
    }

    /** Создаёт клиента без категории доставки. */
    private function createCustomerWithoutDelivery(): MaxUser
    {
        return MaxUser::query()->create([
            'max_user_id' => 12_100,
            'first_name' => 'NoDelivery',
        ]);
    }
}
