<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderManualAdminReadRepositoryInterface;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentFoodOrderManualAdminReadRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** LIKE-метасимволы в q не матчат все ручные заказы. */
    public function test_paginate_manual_orders_escapes_like_wildcards(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Like Place', 'Soup', 100);
        $manager = MaxUser::query()->create([
            'max_user_id' => 56_100,
            'first_name' => 'Manager',
        ]);
        $alice = MaxUser::query()->create([
            'max_user_id' => 56_101,
            'first_name' => 'Alice',
            'last_name' => 'Normal',
        ]);
        $bobPercent = MaxUser::query()->create([
            'max_user_id' => 56_102,
            'first_name' => 'Bob%',
            'last_name' => 'Percent',
        ]);
        $carolUnder = MaxUser::query()->create([
            'max_user_id' => 56_103,
            'first_name' => 'Carol_under',
            'last_name' => 'Score',
        ]);

        foreach ([$alice, $bobPercent, $carolUnder] as $customer) {
            $this->createManualOrder(
                $fixture['restaurant']->id,
                $customer->max_user_id,
                $manager->max_user_id,
            );
        }

        $repository = $this->app->make(FoodOrderManualAdminReadRepositoryInterface::class);

        $percentResult = $repository->paginateManualOrders('%', null, null, 50);
        $this->assertSame(1, $percentResult->total);
        $this->assertSame(56_102, $percentResult->items[0]->maxUserId);

        $underscoreResult = $repository->paginateManualOrders('_', null, null, 50);
        $this->assertSame(1, $underscoreResult->total);
        $this->assertSame(56_103, $underscoreResult->items[0]->maxUserId);

        $substringResult = $repository->paginateManualOrders('Alice', null, null, 50);
        $this->assertSame(1, $substringResult->total);
        $this->assertSame(56_101, $substringResult->items[0]->maxUserId);
    }

    /** Создаёт ручной заказ для теста фильтра LIKE. */
    private function createManualOrder(
        int $restaurantId,
        int $customerMaxUserId,
        int $managerMaxUserId,
    ): FoodOrder {
        $cart = Cart::query()->create([
            'max_user_id' => $customerMaxUserId,
            'created_by_max_user_id' => $managerMaxUserId,
            'restaurant_id' => $restaurantId,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тестовая, 1',
        ]);

        return FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $customerMaxUserId,
            'is_manual' => true,
            'created_by_max_user_id' => $managerMaxUserId,
            'restaurant_id' => $restaurantId,
            'status' => OrderStatus::Confirmed,
            'address_review_status' => OrderReviewStatus::Approved,
            'composition_review_status' => OrderReviewStatus::Approved,
            'payment_review_status' => OrderReviewStatus::Approved,
            'total' => 100,
            'items_total' => 100,
            'delivery_cost' => 0,
            'delivery_address' => 'ул. Тестовая, 1',
            'items_snapshot' => [],
        ]);
    }
}
