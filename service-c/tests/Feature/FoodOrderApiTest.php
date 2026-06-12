<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\CartStatus;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class FoodOrderApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_submit_order_requires_authentication(): void
    {
        $this->postJson('/api/food/orders/submit')
            ->assertUnauthorized();
    }

    public function test_submit_order_rejects_empty_cart(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cart is empty.');
    }

    public function test_submit_order_creates_submitted_order_and_marks_cart_submitted(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Order Place', 'Steak', 700);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])->assertOk();

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::Submitted->value)
            ->assertJsonPath('order.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('order.restaurant_name', 'Order Place')
            ->assertJsonPath('order.total', '1400.00')
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Steak')
            ->assertJsonPath('order.items_snapshot.0.quantity', 2)
            ->assertJsonPath('order.items_snapshot.0.image_url', $fixture['dish']->image_url);

        $this->assertDatabaseHas('max_food_orders', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::Submitted->value,
            'total' => '1400.00',
        ]);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Submitted->value,
        ]);

        $this->assertSame(1, FoodOrder::query()->count());
    }

    public function test_cart_is_empty_after_order_submission(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])->assertCreated();

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);
    }

    public function test_user_can_place_new_order_after_previous_submission(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])->assertCreated();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.total', '200.00');

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('order.total', '200.00');

        $this->assertSame(2, FoodOrder::query()->count());
    }
}
