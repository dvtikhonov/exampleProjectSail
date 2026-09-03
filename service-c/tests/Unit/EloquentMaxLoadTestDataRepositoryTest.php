<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use App\Support\Max\MaxLoadTestUserIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Eloquent data-access для нагрузочного стенда MAX.
 */
class EloquentMaxLoadTestDataRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private MaxLoadTestDataRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->repository = $this->app->make(MaxLoadTestDataRepositoryInterface::class);
    }

    public function test_list_active_restaurant_ids_ordered(): void
    {
        $activeA = FoodTestDataBuilder::createRestaurantWithDish('A Active', 'Dish A');
        $activeB = FoodTestDataBuilder::createRestaurantWithDish('B Active', 'Dish B');
        $inactive = FoodTestDataBuilder::createRestaurantWithDish('Inactive', 'Dish C');
        $inactive['restaurant']->update(['is_active' => false]);

        $ids = $this->repository->listActiveRestaurantIds();

        $this->assertContains($activeA['restaurant']->id, $ids);
        $this->assertContains($activeB['restaurant']->id, $ids);
        $this->assertNotContains($inactive['restaurant']->id, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_enable_unavailable_dishes_only_for_given_restaurants(): void
    {
        $active = FoodTestDataBuilder::createRestaurantWithDish('Active', 'Off Dish');
        $active['dish']->update(['is_available' => false]);

        $other = FoodTestDataBuilder::createRestaurantWithDish('Other', 'Still Off');
        $other['dish']->update(['is_available' => false]);

        $updated = $this->repository->enableUnavailableDishesForRestaurants([
            $active['restaurant']->id,
        ]);

        $this->assertSame(1, $updated);
        $this->assertTrue($active['dish']->fresh()->is_available);
        $this->assertFalse($other['dish']->fresh()->is_available);
    }

    public function test_delete_orders_and_carts_for_max_user_ids(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $loadTestId = MaxLoadTestUserIds::BASE_ID;

        MaxUser::query()->create([
            'max_user_id' => $loadTestId,
            'first_name' => 'LoadTest1',
        ]);
        $other = MaxUser::query()->create([
            'max_user_id' => 1001,
            'first_name' => 'Demo',
        ]);

        $loadCart = Cart::query()->create([
            'max_user_id' => $loadTestId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'Load',
        ]);
        FoodOrder::query()->create([
            'cart_id' => $loadCart->id,
            'max_user_id' => $loadTestId,
            'is_manual' => false,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::Confirmed,
            'address_review_status' => OrderReviewStatus::Approved,
            'composition_review_status' => OrderReviewStatus::Approved,
            'payment_review_status' => OrderReviewStatus::Approved,
            'total' => 100,
            'items_total' => 100,
            'delivery_cost' => 0,
            'delivery_address' => 'Load',
            'items_snapshot' => [],
        ]);

        $keepCart = Cart::query()->create([
            'max_user_id' => $other->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft,
            'delivery_address' => 'Keep',
        ]);

        $ordersDeleted = $this->repository->deleteOrdersForMaxUserIds([$loadTestId]);
        $cartsDeleted = $this->repository->deleteCartsForMaxUserIds([$loadTestId]);

        $this->assertSame(1, $ordersDeleted);
        $this->assertSame(1, $cartsDeleted);
        $this->assertDatabaseMissing('max_food_orders', ['max_user_id' => $loadTestId]);
        $this->assertDatabaseMissing('max_carts', ['id' => $loadCart->id]);
        $this->assertDatabaseHas('max_carts', ['id' => $keepCart->id]);
    }
}
