<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MaxUser;
use App\Services\Food\CartTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class CartTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_calculate_without_category_marks_delivery_not_applicable(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 12_010,
            'first_name' => 'NoCategory',
        ]);

        $totals = app(CartTotalsCalculator::class)->calculate(
            restaurantId: $fixture['restaurant']->id,
            maxUser: $maxUser,
            itemsTotal: 300.0,
        );

        $this->assertSame(300.0, $totals->itemsTotal);
        $this->assertNull($totals->deliveryCost);
        $this->assertSame(300.0, $totals->total);
        $this->assertFalse($totals->deliveryApplicable);
        $this->assertNull($totals->customerCategory);
    }

    public function test_calculate_with_category_applies_tier_delivery_cost(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            tiers: [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 200.00],
            ],
        );

        $maxUser = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']);

        $calculator = app(CartTotalsCalculator::class);

        $belowThreshold = $calculator->calculate(
            restaurantId: $fixture['restaurant']->id,
            maxUser: $maxUser,
            itemsTotal: 999.0,
        );

        $this->assertTrue($belowThreshold->deliveryApplicable);
        $this->assertSame(200.0, $belowThreshold->deliveryCost);
        $this->assertSame(1199.0, $belowThreshold->total);
        $this->assertSame(1000.0, $belowThreshold->nextTierMinTotal);
        $this->assertSame(0.0, $belowThreshold->nextTierDeliveryCost);
        $this->assertSame(1.0, $belowThreshold->amountToNextTier);

        $atThreshold = $calculator->calculate(
            restaurantId: $fixture['restaurant']->id,
            maxUser: $maxUser,
            itemsTotal: 1000.0,
        );

        $this->assertSame(0.0, $atThreshold->deliveryCost);
        $this->assertSame(1000.0, $atThreshold->total);
        $this->assertNull($atThreshold->nextTierMinTotal);
        $this->assertNull($atThreshold->nextTierDeliveryCost);
        $this->assertNull($atThreshold->amountToNextTier);
    }

    public function test_calculate_with_category_and_no_tiers_sets_zero_delivery_cost(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $category = FoodTestDataBuilder::createCustomerCategory();
        $maxUser = FoodTestDataBuilder::createMaxUserWithCategory($category);

        $totals = app(CartTotalsCalculator::class)->calculate(
            restaurantId: $fixture['restaurant']->id,
            maxUser: $maxUser,
            itemsTotal: 100.0,
        );

        $this->assertTrue($totals->deliveryApplicable);
        $this->assertSame(0.0, $totals->deliveryCost);
        $this->assertSame(100.0, $totals->total);
    }
}
