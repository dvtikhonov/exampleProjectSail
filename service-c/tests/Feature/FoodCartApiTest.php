<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\TestCase;

class FoodCartApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;

    public function test_cart_show_returns_null_when_no_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);
    }

    public function test_add_item_creates_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 199.50);

        $response = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers']);

        $response
            ->assertOk()
            ->assertJsonPath('cart.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('cart.status', CartStatus::Draft->value)
            ->assertJsonPath('cart.items.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.unit_price', '199.50')
            ->assertJsonPath('cart.items.0.line_total', '399.00')
            ->assertJsonPath('cart.total', '399.00');

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    public function test_add_item_validates_payload(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->postJson('/api/food/cart/items', [], $auth['headers'])
            ->assertUnprocessable();
    }

    public function test_add_item_rejects_unavailable_dish(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['is_available' => false]);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Dish is not available.');
    }

    public function test_add_item_rejects_dish_from_another_restaurant_when_cart_exists(): void
    {
        $auth = $this->authenticateMaxUser();
        $first = FoodTestDataBuilder::createRestaurantWithDish('First', 'Soup', 100);
        $second = FoodTestDataBuilder::createRestaurantWithDish('Second', 'Salad', 120);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $first['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $second['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cart already contains items from another restaurant. Clear the cart before adding dishes from a different restaurant.');
    }

    public function test_update_cart_item_quantity(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $addResponse = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers']);

        $cartItemId = (int) $addResponse->json('cart.items.0.id');

        $this->patchJson('/api/food/cart/items/'.$cartItemId, [
            'quantity' => 3,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.total', '300.00');
    }

    public function test_delete_cart_item_returns_null_cart_when_last_item_removed(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $addResponse = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers']);

        $cartItemId = (int) $addResponse->json('cart.items.0.id');

        $this->deleteJson('/api/food/cart/items/'.$cartItemId, [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);

        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    public function test_cart_item_operations_reject_foreign_cart_item(): void
    {
        $auth = $this->authenticateMaxUser();
        $otherUser = $this->authenticateMaxUser(
            MaxUser::query()->create([
                'max_user_id' => 88_002,
                'first_name' => 'Other',
            ]),
        );

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $cart = Cart::query()->create([
            'max_user_id' => $otherUser['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft,
        ]);

        $cartItem = CartItem::query()->create([
            'cart_id' => $cart->id,
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ]);

        $this->patchJson('/api/food/cart/items/'.$cartItem->id, [
            'quantity' => 2,
        ], $auth['headers'])
            ->assertNotFound();

        $this->deleteJson('/api/food/cart/items/'.$cartItem->id, [], $auth['headers'])
            ->assertNotFound();
    }

    public function test_add_item_increments_existing_dish_quantity(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 50);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.total', '150.00');
    }
}
