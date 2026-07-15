<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;
use App\Models\FoodOrderMessage;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class CustomerOrderListApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mock(FoodOrderMaxNotifierInterface::class)->shouldIgnoreMissing();
    }

    /** Клиент получает список только своих заказов. */
    public function test_customer_order_list_returns_only_own_orders(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery('Place A', 'Dish A', 100);
        $customerA = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_101);
        $customerB = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_102);

        $orderAId = $this->submitOrderForUser($customerA, $fixture['dish']->id);
        $this->submitOrderForUser($customerB, $fixture['dish']->id);

        $authA = $this->authenticateMaxUser($customerA);

        $this->getJson('/api/food/orders', $authA['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderAId)
            ->assertJsonPath('orders.0.restaurant_name', 'Place A')
            ->assertJsonPath('orders.0.status', OrderStatus::PendingReview->value);
    }

    /** Список заказов клиента включает статистику чата. */
    public function test_customer_order_list_includes_chat_stats(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery('Chat Stats', 'Ramen', 400);
        $customer = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_201);
        $admin = MaxUser::query()->create([
            'max_user_id' => 10_003,
            'first_name' => 'AddressAdmin',
        ]);
        $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($admin),
            FoodOrderAdminRole::AddressReviewer,
        );

        $orderId = $this->submitOrderForUser($customer, $fixture['dish']->id);

        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Сообщение клиента',
        ]);
        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $admin->max_user_id,
            'body' => 'Ответ админа',
        ]);

        $auth = $this->authenticateMaxUser($customer);

        $response = $this->getJson('/api/food/orders', $auth['headers']);

        $response
            ->assertOk()
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.unread_count', 1);

        $this->assertNotNull($response->json('orders.0.last_message_at'));
    }

    /** Список заказов пуст, если заказов нет. */
    public function test_customer_order_list_is_empty_without_orders(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/orders', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    /** Клиент может просмотреть детали своего заказа. */
    public function test_customer_can_view_own_order_details(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery('Detail Place', 'Steak', 700);
        $customer = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_301);
        $orderId = $this->submitOrderForUser($customer, $fixture['dish']->id, quantity: 2);
        $auth = $this->authenticateMaxUser($customer);

        $this->getJson("/api/food/orders/{$orderId}", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.restaurant_name', 'Detail Place')
            ->assertJsonPath('order.items_total', '1400.00')
            ->assertJsonPath('order.delivery_address', 'ул. Примерная, 1')
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Steak');
    }

    /** Клиент не может просмотреть чужой заказ. */
    public function test_customer_cannot_view_another_users_order(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery();
        $owner = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_401);
        $orderId = $this->submitOrderForUser($owner, $fixture['dish']->id);

        $otherAuth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 66_402,
            'first_name' => 'OtherCustomer',
        ]));

        $this->getJson("/api/food/orders/{$orderId}", $otherAuth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Список заказов возвращает несколько заказов, сначала новые. */
    public function test_customer_order_list_returns_multiple_orders_newest_first(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery('Multi', 'Pizza', 200);
        $customer = FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category'], 66_501);

        $firstOrderId = $this->submitOrderForUser($customer, $fixture['dish']->id);
        $secondOrderId = $this->submitOrderForUser($customer, $fixture['dish']->id);

        FoodOrder::query()->whereKey($firstOrderId)->update([
            'created_at' => now()->subMinute(),
        ]);
        FoodOrder::query()->whereKey($secondOrderId)->update([
            'created_at' => now(),
        ]);

        $auth = $this->authenticateMaxUser($customer);

        $this->getJson('/api/food/orders', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.id', $secondOrderId)
            ->assertJsonPath('orders.1.id', $firstOrderId);
    }

    /** Отправляет заказ от имени пользователя. */
    private function submitOrderForUser(MaxUser $customer, int $dishId, int $quantity = 1): int
    {
        $auth = $this->authenticateMaxUser($customer);

        $this->addItemToCart($auth, $dishId, $quantity);
        $this->setCartDeliveryAddress($auth);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response->assertCreated();

        return (int) $response->json('order.id');
    }

    /**
     * @param  array{headers: array<string, string>}  $auth
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
