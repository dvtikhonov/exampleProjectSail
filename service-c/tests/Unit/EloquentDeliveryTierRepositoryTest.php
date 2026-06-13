<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\Food\EloquentDeliveryTierRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentDeliveryTierRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_find_tiers_for_returns_empty_list_when_matrix_missing(): void
    {
        $repository = app(EloquentDeliveryTierRepository::class);

        $this->assertSame([], $repository->findTiersFor(1, 1));
    }

    public function test_find_tiers_for_returns_tiers_sorted_by_min_items_total_desc(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            tiers: [
                ['min_items_total' => 0.00, 'delivery_cost' => 175.50],
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
            ],
        );

        $repository = app(EloquentDeliveryTierRepository::class);

        $tiers = $repository->findTiersFor(
            $fixture['restaurant']->id,
            $fixture['customer_category']->id,
        );

        $this->assertCount(2, $tiers);
        $this->assertSame(1000.0, $tiers[0]->minItemsTotal);
        $this->assertSame(0.0, $tiers[0]->deliveryCost);
        $this->assertSame(0.0, $tiers[1]->minItemsTotal);
        $this->assertSame(175.5, $tiers[1]->deliveryCost);
    }

    public function test_find_tiers_for_distinguishes_restaurant_and_category_pairs(): void
    {
        $first = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            restaurantName: 'First',
            customerCategoryName: 'Standard',
            tiers: [['min_items_total' => 0.00, 'delivery_cost' => 100.00]],
        );
        $second = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            restaurantName: 'Second',
            customerCategoryName: 'VIP',
            tiers: [['min_items_total' => 0.00, 'delivery_cost' => 250.00]],
        );

        $repository = app(EloquentDeliveryTierRepository::class);

        $firstTiers = $repository->findTiersFor(
            $first['restaurant']->id,
            $first['customer_category']->id,
        );
        $secondTiers = $repository->findTiersFor(
            $second['restaurant']->id,
            $second['customer_category']->id,
        );

        $this->assertSame(100.0, $firstTiers[0]->deliveryCost);
        $this->assertSame(250.0, $secondTiers[0]->deliveryCost);
        $this->assertSame([], $repository->findTiersFor(
            $first['restaurant']->id,
            $second['customer_category']->id,
        ));
    }
}
