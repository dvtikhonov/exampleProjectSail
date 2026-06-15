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
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class FoodCartApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

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
            ->assertJsonPath('cart.items.0.image_url', '/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertJsonPath('cart.items_total', '399.00')
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '399.00')
            ->assertJsonPath('cart.delivery_applicable', false)
            ->assertJsonPath('cart.customer_category', null);

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
            ->assertJsonPath('cart.items_total', '300.00')
            ->assertJsonPath('cart.delivery_cost', null)
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
            ->assertJsonPath('cart.items_total', '150.00')
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '150.00');
    }

    public function test_cart_includes_delivery_cost_for_user_with_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 199.50,
            tiers: [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 150.00],
            ],
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '399.00')
            ->assertJsonPath('cart.delivery_cost', '150.00')
            ->assertJsonPath('cart.total', '549.00')
            ->assertJsonPath('cart.delivery_applicable', true)
            ->assertJsonPath('cart.customer_category.id', $fixture['customer_category']->id)
            ->assertJsonPath('cart.customer_category.name', 'Standard');
    }

    public function test_cart_applies_delivery_tier_at_999_items_total(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 999.00,
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '999.00')
            ->assertJsonPath('cart.delivery_cost', '200.00')
            ->assertJsonPath('cart.total', '1199.00')
            ->assertJsonPath('cart.delivery_applicable', true);
    }

    public function test_cart_applies_free_delivery_tier_at_1000_items_total(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 500.00,
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '1000.00')
            ->assertJsonPath('cart.delivery_cost', '0.00')
            ->assertJsonPath('cart.total', '1000.00')
            ->assertJsonPath('cart.delivery_applicable', true);
    }

    public function test_cart_without_category_excludes_delivery_from_total(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(price: 250.00);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '250.00')
            ->assertJsonPath('cart.delivery_applicable', false)
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '250.00')
            ->assertJsonPath('cart.customer_category', null);
    }

    public function test_patch_cart_delivery_address(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $address = 'ул. Примерная, 1';

        $this->patchJson('/api/food/cart', [
            'delivery_address' => $address,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_address', $address);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
            'delivery_address' => $address,
        ]);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $auth['user']->max_user_id,
            'delivery_address' => $address,
        ]);
    }

    public function test_new_cart_prefills_delivery_address_from_max_user(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $address = 'ул. Сохранённая, 5';

        $auth['user']->update(['delivery_address' => $address]);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_address', $address);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
            'delivery_address' => $address,
        ]);
    }

    public function test_patch_cart_delivery_address_validates_payload(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->patchJson('/api/food/cart', [], $auth['headers'])
            ->assertUnprocessable();
    }

    public function test_patch_cart_delivery_address_requires_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->patchJson('/api/food/cart', [
            'delivery_address' => 'ул. Примерная, 1',
        ], $auth['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Cart is empty.');
    }
}
