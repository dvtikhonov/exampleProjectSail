<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\DTO\Food\OrderDto;
use App\Enums\Food\CartStatus;
use App\Enums\Food\OrderStatus;
use App\Models\Dish;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class FoodOrderApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;
    use ResolvesDishImageUrl;

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
        $this->assertSame(OrderStatus::PendingReview->value, $capturedOrder->status);
        $this->assertSame('Notify Place', $capturedOrder->restaurantName);
        $this->assertSame('1000.00', $capturedOrder->itemsTotal);
        $this->assertSame($address, $capturedOrder->deliveryAddress);
        $this->assertSame('Burger', $capturedOrder->itemsSnapshot[0]['dish_name'] ?? null);
    }

    public function test_submit_order_creates_pending_review_order_and_marks_cart_submitted(): void
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
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('order.restaurant_name', 'Order Place')
            ->assertJsonPath('order.items_total', '1400.00')
            ->assertJsonPath('order.delivery_applicable', true)
            ->assertJsonPath('order.delivery_cost', '0.00')
            ->assertJsonPath('order.total', '1400.00')
            ->assertJsonPath('order.delivery_address', $address)
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Steak')
            ->assertJsonPath('order.items_snapshot.0.quantity', 2)
            ->assertJsonPath('order.items_snapshot.0.image_url', $this->expectedDishImageUrlForModel($fixture['dish']));

        $this->assertDatabaseHas('max_food_orders', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview->value,
            'address_review_status' => 'pending',
            'composition_review_status' => 'pending',
            'payment_review_status' => 'pending',
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

    public function test_submit_order_includes_combo_metadata_in_items_snapshot(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Combo Place',
            'Burger',
            320,
        );
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Fries',
            'price' => 180,
        ]);
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );
        $comboRef = '550e8400-e29b-41d4-a716-446655440000';

        $this->addComboItemToCart($auth, $fixture['dish']->id, 3, $comboRef, $sideDish->id)
            ->assertOk();
        $this->addComboItemToCart($auth, $sideDish->id, 3, $comboRef, $fixture['dish']->id)
            ->assertOk();
        $this->setCartDeliveryAddress($auth);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.items_total', '1500.00')
            ->assertJsonPath('order.items_snapshot.0.combo_ref', $comboRef)
            ->assertJsonPath('order.items_snapshot.0.combo_partner_dish_ids.0', $sideDish->id)
            ->assertJsonPath('order.items_snapshot.1.combo_ref', $comboRef)
            ->assertJsonPath('order.items_snapshot.1.combo_partner_dish_ids.0', $fixture['dish']->id)
            ->assertJsonPath('order.items_snapshot.0.quantity', 3)
            ->assertJsonPath('order.items_snapshot.1.quantity', 3);

        $order = FoodOrder::query()->firstOrFail();
        $snapshot = $order->items_snapshot;

        $this->assertSame($comboRef, $snapshot[0]['combo_ref']);
        $this->assertSame([$sideDish->id], $snapshot[0]['combo_partner_dish_ids']);
        $this->assertSame($comboRef, $snapshot[1]['combo_ref']);
        $this->assertSame([$fixture['dish']->id], $snapshot[1]['combo_partner_dish_ids']);
    }

    public function test_submit_order_keeps_mutual_snapshot_links_for_multiple_combos(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Multi Combo Place',
            'Burger',
            320,
        );
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $dessertCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Desserts',
            'sort_order' => 3,
        ]);
        $drinkCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Drinks',
            'sort_order' => 4,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Fries',
            'price' => 180,
        ]);
        $dessertDish = Dish::factory()->create([
            'menu_category_id' => $dessertCategory->id,
            'name' => 'Cake',
            'price' => 150,
        ]);
        $drinkDish = Dish::factory()->create([
            'menu_category_id' => $drinkCategory->id,
            'name' => 'Cola',
            'price' => 90,
        ]);
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );
        $firstComboRef = '550e8400-e29b-41d4-a716-446655440000';
        $secondComboRef = '550e8400-e29b-41d4-a716-446655440001';

        $this->addComboItemToCart($auth, $fixture['dish']->id, 2, $firstComboRef, $sideDish->id)
            ->assertOk();
        $this->addComboItemToCart($auth, $sideDish->id, 2, $firstComboRef, $fixture['dish']->id)
            ->assertOk();
        $this->addComboItemToCart($auth, $dessertDish->id, 1, $secondComboRef, $drinkDish->id)
            ->assertOk();
        $this->addComboItemToCart($auth, $drinkDish->id, 1, $secondComboRef, $dessertDish->id)
            ->assertOk();
        $this->setCartDeliveryAddress($auth);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.items_total', '1240.00');

        $snapshot = $response->json('order.items_snapshot');
        $this->assertCount(4, $snapshot);

        $itemsByDishId = collect($snapshot)->keyBy('dish_id');
        $this->assertSame($firstComboRef, $itemsByDishId[$fixture['dish']->id]['combo_ref']);
        $this->assertSame([$sideDish->id], $itemsByDishId[$fixture['dish']->id]['combo_partner_dish_ids']);
        $this->assertSame(2, $itemsByDishId[$fixture['dish']->id]['quantity']);
        $this->assertSame($firstComboRef, $itemsByDishId[$sideDish->id]['combo_ref']);
        $this->assertSame([$fixture['dish']->id], $itemsByDishId[$sideDish->id]['combo_partner_dish_ids']);
        $this->assertSame(2, $itemsByDishId[$sideDish->id]['quantity']);
        $this->assertSame($secondComboRef, $itemsByDishId[$dessertDish->id]['combo_ref']);
        $this->assertSame([$drinkDish->id], $itemsByDishId[$dessertDish->id]['combo_partner_dish_ids']);
        $this->assertSame(1, $itemsByDishId[$dessertDish->id]['quantity']);
        $this->assertSame($secondComboRef, $itemsByDishId[$drinkDish->id]['combo_ref']);
        $this->assertSame([$dessertDish->id], $itemsByDishId[$drinkDish->id]['combo_partner_dish_ids']);
        $this->assertSame(1, $itemsByDishId[$drinkDish->id]['quantity']);

        $orderSnapshot = FoodOrder::query()->firstOrFail()->items_snapshot;
        $this->assertSame($snapshot, $orderSnapshot);
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
     * @param  array{headers: array<string, string>, user: MaxUser}  $auth
     */
    private function addComboItemToCart(
        array $auth,
        int $dishId,
        int $quantity,
        string $comboRef,
        int $comboPartnerDishId,
    ): TestResponse {
        return $this->postJson('/api/food/cart/items', [
            'dish_id' => $dishId,
            'quantity' => $quantity,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $comboPartnerDishId,
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
