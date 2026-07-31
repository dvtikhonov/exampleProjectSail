<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\FoodOrderMessage;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminManualOrderListApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** max_manager может фильтровать ручные заказы по статусу. */
    public function test_max_manager_can_filter_manual_orders_by_status(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 55_001,
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 55_100,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Manual Place', 'Soup', 200);

        $confirmedId = $this->createManualOrder(
            customer: $customer,
            manager: $manager,
            restaurantId: $fixture['restaurant']->id,
            status: OrderStatus::Confirmed,
            dishName: 'Soup',
        );
        $this->createManualOrder(
            customer: $customer,
            manager: $manager,
            restaurantId: $fixture['restaurant']->id,
            status: OrderStatus::Rejected,
            dishName: 'Soup',
        );

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($manager),
            FoodOrderAdminRole::MaxManager,
        );

        $this->getJson(
            '/api/food/admin/manual-orders?max_user_id=55001&status=confirmed',
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $confirmedId)
            ->assertJsonPath('orders.0.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_amount', '200.00');

        $this->getJson(
            '/api/food/admin/manual-orders?max_user_id=55001',
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.total_amount', '400.00');
    }

    /** Невалидный статус списка ручных заказов отклоняется. */
    public function test_manual_orders_list_rejects_invalid_status(): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 55_101,
                'first_name' => 'Manager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );

        $this->getJson(
            '/api/food/admin/manual-orders?status=submitted',
            $auth['headers'],
        )->assertUnprocessable();
    }

    /** max_manager может открыть карточку ручного заказа с составом. */
    public function test_max_manager_can_show_manual_order_detail(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 55_002,
            'first_name' => 'Anna',
            'last_name' => 'Sidorova',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 55_102,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Manual Cafe', 'Salad', 350);

        $orderId = $this->createManualOrder(
            customer: $customer,
            manager: $manager,
            restaurantId: $fixture['restaurant']->id,
            status: OrderStatus::Confirmed,
            dishName: 'Salad',
            unitPrice: '350.00',
            quantity: 2,
            deliveryCost: '100.00',
        );

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($manager),
            FoodOrderAdminRole::MaxManager,
        );

        $this->getJson("/api/food/admin/manual-orders/{$orderId}", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('order.restaurant_name', 'Manual Cafe')
            ->assertJsonPath('order.delivery_applicable', true)
            ->assertJsonPath('order.delivery_cost', '100.00')
            ->assertJsonPath('order.items_total', '700.00')
            ->assertJsonPath('order.total', '800.00')
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Salad')
            ->assertJsonPath('order.items_snapshot.0.quantity', 2)
            ->assertJsonPath('order.customer.max_user_id', 55_002)
            ->assertJsonPath('order.has_messages', false);
    }

    /** Карточка ручного заказа помечает наличие сообщений чата. */
    public function test_manual_order_detail_reports_has_messages_when_chat_exists(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 55_004,
            'first_name' => 'Chat',
            'last_name' => 'Customer',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 55_104,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Chat Cafe', 'Soup', 200);

        $orderId = $this->createManualOrder(
            customer: $customer,
            manager: $manager,
            restaurantId: $fixture['restaurant']->id,
            status: OrderStatus::Confirmed,
            dishName: 'Soup',
        );

        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Есть вопрос по доставке',
        ]);

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($manager),
            FoodOrderAdminRole::MaxManager,
        );

        $this->getJson("/api/food/admin/manual-orders/{$orderId}", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.has_messages', true);
    }

    /** Просмотр несуществующего или не-ручного заказа возвращает 404. */
    public function test_show_returns_not_found_for_non_manual_order(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 55_003,
            'first_name' => 'Client',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 55_103,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $order = FoodOrder::query()->create([
            'max_user_id' => $customer->max_user_id,
            'is_manual' => false,
            'created_by_max_user_id' => null,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'items_total' => 100,
            'delivery_cost' => null,
            'total' => 100,
            'delivery_address' => 'ул. Тестовая, 1',
            'items_snapshot' => [],
            'cart_id' => Cart::query()->create([
                'max_user_id' => $customer->max_user_id,
                'created_by_max_user_id' => null,
                'restaurant_id' => $fixture['restaurant']->id,
                'status' => CartStatus::Submitted,
                'delivery_address' => 'ул. Тестовая, 1',
            ])->id,
        ]);

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($manager),
            FoodOrderAdminRole::MaxManager,
        );

        $this->getJson("/api/food/admin/manual-orders/{$order->id}", $auth['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Заказ не найден.');
    }

    /** Список и карточка недоступны без роли max_manager. */
    public function test_manual_orders_forbidden_without_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/manual-orders', $auth['headers'])
            ->assertForbidden();

        $this->getJson('/api/food/admin/manual-orders/1', $auth['headers'])
            ->assertForbidden();
    }

    /**
     * Создаёт ручной заказ для тестов списка/карточки.
     */
    private function createManualOrder(
        MaxUser $customer,
        MaxUser $manager,
        int $restaurantId,
        OrderStatus $status,
        string $dishName,
        string $unitPrice = '200.00',
        int $quantity = 1,
        ?string $deliveryCost = null,
    ): int {
        $itemsTotal = number_format((float) $unitPrice * $quantity, 2, '.', '');
        $total = number_format((float) $itemsTotal + (float) ($deliveryCost ?? '0'), 2, '.', '');

        $cart = Cart::query()->create([
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager->max_user_id,
            'restaurant_id' => $restaurantId,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Ручная, 10',
        ]);

        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $customer->max_user_id,
            'is_manual' => true,
            'created_by_max_user_id' => $manager->max_user_id,
            'restaurant_id' => $restaurantId,
            'status' => $status,
            'address_review_status' => OrderReviewStatus::Approved,
            'composition_review_status' => OrderReviewStatus::Approved,
            'payment_review_status' => OrderReviewStatus::Approved,
            'items_total' => $itemsTotal,
            'delivery_cost' => $deliveryCost,
            'total' => $total,
            'delivery_address' => 'ул. Ручная, 10',
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => $dishName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $itemsTotal,
                    'image_url' => null,
                ],
            ],
        ]);

        return $order->id;
    }
}
