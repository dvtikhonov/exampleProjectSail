<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\DTO\Food\OrderDto;
use App\Enums\Food\CartStatus;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    public function test_submit_order_triggers_max_notification_with_created_order(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Notify Place',
            'Burger',
            500,
        );
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );
        $address = 'ул. Примерная, 1';

        $capturedOrder = null;
        $capturedUser = null;

        $notifier = $this->createMock(FoodOrderMaxNotifierInterface::class);
        $notifier
            ->expects($this->once())
            ->method('notify')
            ->willReturnCallback(function (OrderDto $order, MaxUser $user) use (&$capturedOrder, &$capturedUser): void {
                $capturedOrder = $order;
                $capturedUser = $user;
            });

        $this->app->instance(FoodOrderMaxNotifierInterface::class, $notifier);

        $this->addItemToCart($auth, $fixture['dish']->id, 2);
        $this->setCartDeliveryAddress($auth, $address);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.restaurant_name', 'Notify Place')
            ->assertJsonPath('order.items_total', '1000.00')
            ->assertJsonPath('order.delivery_address', $address);

        $this->assertNotNull($capturedOrder);
        $this->assertNotNull($capturedUser);
        $this->assertSame($auth['user']->max_user_id, $capturedUser->max_user_id);
        $this->assertSame($response->json('order.id'), $capturedOrder->id);
        $this->assertSame(OrderStatus::Submitted->value, $capturedOrder->status);
        $this->assertSame('Notify Place', $capturedOrder->restaurantName);
        $this->assertSame('1000.00', $capturedOrder->itemsTotal);
        $this->assertSame($address, $capturedOrder->deliveryAddress);
        $this->assertSame('Burger', $capturedOrder->itemsSnapshot[0]['dish_name'] ?? null);
    }

    public function test_submit_order_creates_submitted_order_and_marks_cart_submitted(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Order Place',
            'Steak',
            700,
        );
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );
        $address = 'ул. Примерная, 1';

        $this->addItemToCart($auth, $fixture['dish']->id, 2);
        $this->setCartDeliveryAddress($auth, $address);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::Submitted->value)
            ->assertJsonPath('order.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('order.restaurant_name', 'Order Place')
            ->assertJsonPath('order.items_total', '1400.00')
            ->assertJsonPath('order.delivery_applicable', true)
            ->assertJsonPath('order.delivery_cost', '0.00')
            ->assertJsonPath('order.total', '1400.00')
            ->assertJsonPath('order.delivery_address', $address)
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Steak')
            ->assertJsonPath('order.items_snapshot.0.quantity', 2)
            ->assertJsonPath('order.items_snapshot.0.image_url', '/api/food/dishes/'.$fixture['dish']->id.'/image');

        $this->assertDatabaseHas('max_food_orders', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::Submitted->value,
            'items_total' => '1400.00',
            'delivery_cost' => '0.00',
            'total' => '1400.00',
            'delivery_address' => $address,
        ]);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Submitted->value,
        ]);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $auth['user']->max_user_id,
            'delivery_address' => $address,
        ]);

        $this->assertSame(1, FoodOrder::query()->count());
    }

    public function test_cart_is_empty_after_order_submission(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery();
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->addItemToCart($auth, $fixture['dish']->id, 1);
        $this->setCartDeliveryAddress($auth);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])->assertCreated();

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);
    }

    public function test_user_can_place_new_order_after_previous_submission(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 100,
        );
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->addItemToCart($auth, $fixture['dish']->id, 1);
        $this->setCartDeliveryAddress($auth);
        $this->postJson('/api/food/orders/submit', [], $auth['headers'])->assertCreated();

        $this->addItemToCart($auth, $fixture['dish']->id, 2)
            ->assertJsonPath('cart.items_total', '200.00')
            ->assertJsonPath('cart.delivery_cost', '200.00')
            ->assertJsonPath('cart.total', '400.00')
            ->assertJsonPath('cart.delivery_address', 'ул. Примерная, 1');

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('order.items_total', '200.00')
            ->assertJsonPath('order.delivery_cost', '200.00')
            ->assertJsonPath('order.total', '400.00');

        $this->assertSame(2, FoodOrder::query()->count());
    }

    public function test_submit_order_rejects_missing_delivery_address(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery();
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->addItemToCart($auth, $fixture['dish']->id, 1);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите адрес доставки.');
    }

    public function test_submit_order_rejects_missing_delivery_address_for_user_without_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->authenticateMaxUser();

        $this->addItemToCart($auth, $fixture['dish']->id, 1);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите адрес доставки.');
    }

    public function test_submit_order_allows_user_without_category_when_address_present(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery();
        $auth = $this->authenticateMaxUser();
        $address = 'ул. Примерная, 1';

        $this->addItemToCart($auth, $fixture['dish']->id, 1);
        $this->setCartDeliveryAddress($auth, $address);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('order.items_total', '250.00')
            ->assertJsonPath('order.delivery_applicable', false)
            ->assertJsonPath('order.delivery_cost', null)
            ->assertJsonPath('order.total', '250.00')
            ->assertJsonPath('order.delivery_address', $address);

        $this->assertDatabaseHas('max_food_orders', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'items_total' => '250.00',
            'delivery_cost' => null,
            'total' => '250.00',
            'delivery_address' => $address,
        ]);
    }

    public function test_submit_order_allows_category_without_configured_tiers(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $category = FoodTestDataBuilder::createCustomerCategory();
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($category),
        );

        $this->addItemToCart($auth, $fixture['dish']->id, 1);
        $this->setCartDeliveryAddress($auth);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('order.delivery_cost', '0.00');
    }

    public function test_submit_order_updates_saved_user_delivery_address_when_changed(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(price: 100);
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );
        $defaultAddress = 'ул. Примерная, 1';
        $changedAddress = 'ул. Разовая, 9';

        $auth['user']->update(['delivery_address' => $defaultAddress]);

        $this->addItemToCart($auth, $fixture['dish']->id, 1)
            ->assertJsonPath('cart.delivery_address', $defaultAddress);

        $this->setCartDeliveryAddress($auth, $changedAddress);

        $this->postJson('/api/food/orders/submit', [], $auth['headers'])
            ->assertCreated()
            ->assertJsonPath('order.delivery_address', $changedAddress);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $auth['user']->max_user_id,
            'delivery_address' => $changedAddress,
        ]);
    }

    /**
     * @param  array{headers: array<string, string>, user: MaxUser}  $auth
     */
    private function addItemToCart(array $auth, int $dishId, int $quantity): TestResponse
    {
        return $this->postJson('/api/food/cart/items', [
            'dish_id' => $dishId,
            'quantity' => $quantity,
        ], $auth['headers']);
    }

    /**
     * @param  array{headers: array<string, string>}  $auth
     */
    private function setCartDeliveryAddress(array $auth, string $address = 'ул. Примерная, 1'): void
    {
        $this->patchJson('/api/food/cart', [
            'delivery_address' => $address,
        ], $auth['headers'])->assertOk();
    }
}
