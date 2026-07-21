<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Enums\Food\CartStatus;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Models\Cart;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminManualOrderApiTest extends TestCase
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

    /** Manual-orders требуют аутентификацию. */
    public function test_manual_orders_require_authentication(): void
    {
        $this->getJson('/api/food/admin/manual-orders/users')
            ->assertUnauthorized();
    }

    /** Без роли max_manager возвращается 403. */
    public function test_manual_orders_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/manual-orders/users', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Чужая админ-роль не открывает manual-orders. */
    public function test_manual_orders_forbidden_with_menu_manager_role(): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_005,
                'first_name' => 'MenuManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );

        $this->getJson('/api/food/admin/manual-orders/users', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** max_manager может получить список пользователей. */
    public function test_max_manager_can_list_users(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 55_001,
            'first_name' => 'Alice',
            'last_name' => 'Client',
            'username' => 'alice_client',
            'delivery_address' => 'ул. Клиентская, 1',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 55_002,
            'first_name' => 'Bob',
            'username' => 'bob_user',
        ]);

        $this->getJson('/api/food/admin/manual-orders/users', $manager['headers'])
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonFragment([
                'max_user_id' => 55_001,
                'first_name' => 'Alice',
                'last_name' => 'Client',
                'username' => 'alice_client',
                'delivery_address' => 'ул. Клиентская, 1',
            ]);
    }

    /** Поиск пользователей фильтрует по имени и username. */
    public function test_max_manager_can_search_users(): void
    {
        $manager = $this->maxManagerAuth();
        MaxUser::query()->create([
            'max_user_id' => 55_010,
            'first_name' => 'FindMe',
            'username' => 'unique_login',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 55_011,
            'first_name' => 'Other',
            'username' => 'other_user',
        ]);

        $this->getJson('/api/food/admin/manual-orders/users?q=unique_login', $manager['headers'])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('users.0.max_user_id', 55_010)
            ->assertJsonPath('users.0.username', 'unique_login');
    }

    /** CRUD ручной корзины от имени клиента. */
    public function test_max_manager_can_manage_manual_cart_for_customer(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Manual Place',
            'Soup',
            300,
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_020,
            firstName: 'CartCustomer',
        );
        $customer->update(['delivery_address' => 'ул. Prefill, 7']);

        $addResponse = $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            2,
        );

        $addResponse
            ->assertOk()
            ->assertJsonPath('cart.status', CartStatus::Draft->value)
            ->assertJsonPath('cart.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('cart.items.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.delivery_address', 'ул. Prefill, 7');

        $itemId = (int) $addResponse->json('cart.items.0.id');

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);

        $this->getJson(
            '/api/food/admin/manual-orders/cart?max_user_id='.$customer->max_user_id,
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('cart.items.0.id', $itemId)
            ->assertJsonPath('delivery_address', 'ул. Prefill, 7');

        $this->patchJson('/api/food/admin/manual-orders/cart/items/'.$itemId, [
            'max_user_id' => $customer->max_user_id,
            'quantity' => 3,
        ], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3);

        $this->patchJson('/api/food/admin/manual-orders/cart', [
            'max_user_id' => $customer->max_user_id,
            'delivery_address' => 'ул. Новая, 9',
        ], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_address', 'ул. Новая, 9');

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $customer->max_user_id,
            'delivery_address' => 'ул. Новая, 9',
        ]);

        $this->deleteJson(
            '/api/food/admin/manual-orders/cart/items/'.$itemId.'?max_user_id='.$customer->max_user_id,
            [],
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('cart', null);

        $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            1,
        )->assertOk();

        $this->deleteJson(
            '/api/food/admin/manual-orders/cart?max_user_id='.$customer->max_user_id,
            [],
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('cart', null);

        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    /** Ручная корзина не затрагивает личную draft-корзину клиента. */
    public function test_manual_cart_does_not_affect_customer_personal_draft_cart(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Isolate Place',
            'Salad',
            250,
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_030,
            firstName: 'OwnCartCustomer',
        );
        $customerAuth = $this->authenticateMaxUser($customer);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $customerAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 1);

        $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            5,
        )->assertOk();

        $this->assertSame(2, Cart::query()->where('max_user_id', $customer->max_user_id)->count());

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => null,
            'status' => CartStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);

        $this->getJson('/api/food/cart', $customerAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 1);

        $this->getJson(
            '/api/food/admin/manual-orders/cart?max_user_id='.$customer->max_user_id,
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 5);
    }

    /** Submit создаёт ручной заказ клиента с флагами is_manual. */
    public function test_submit_manual_order_creates_order_for_customer(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Submit Place',
            'Steak',
            700,
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_040,
            firstName: 'OrderCustomer',
        );
        $address = 'ул. Заказчика, 12';

        $capturedCustomerOrder = null;
        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyConfirmed')
            ->willReturnCallback(function (FoodOrder $order) use (&$capturedCustomerOrder): void {
                $capturedCustomerOrder = $order;
            });
        $customerNotifier->expects($this->never())->method('notifySubmitted');
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            2,
        )->assertOk();

        $this->patchJson('/api/food/admin/manual-orders/cart', [
            'max_user_id' => $customer->max_user_id,
            'delivery_address' => $address,
        ], $manager['headers'])->assertOk();

        $response = $this->postJson('/api/food/admin/manual-orders/submit', [
            'max_user_id' => $customer->max_user_id,
        ], $manager['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('order.restaurant_name', 'Submit Place')
            ->assertJsonPath('order.delivery_address', $address)
            ->assertJsonPath('order.items_total', '1400.00');

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $response->json('order.id'),
            'max_user_id' => $customer->max_user_id,
            'is_manual' => 1,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'status' => OrderStatus::Confirmed->value,
            'address_review_status' => OrderReviewStatus::Approved->value,
            'composition_review_status' => OrderReviewStatus::Approved->value,
            'payment_review_status' => OrderReviewStatus::Approved->value,
            'address_reviewed_by' => $manager['user']->max_user_id,
            'composition_reviewed_by' => $manager['user']->max_user_id,
            'payment_reviewed_by' => $manager['user']->max_user_id,
            'delivery_address' => $address,
        ]);

        $this->assertNotNull($capturedCustomerOrder);
        $this->assertTrue($capturedCustomerOrder->is_manual);
        $this->assertSame($customer->max_user_id, $capturedCustomerOrder->max_user_id);
        $this->assertSame($manager['user']->max_user_id, $capturedCustomerOrder->created_by_max_user_id);
        $this->assertSame(OrderStatus::Confirmed, $capturedCustomerOrder->status);
    }

    /** При ручном submit confirm-уведомления уходят активным max_manager (+ деталка оформившему), не клиенту. */
    public function test_manual_submit_notifies_active_max_managers_not_customer(): void
    {
        config([
            'max.ui_stand.mini_app_url' => '',
            'max.public_app_url' => '',
            'max.webhook.url' => '',
            'max.bot_username' => '',
            'max.ui_stand.recipient_chat_ids' => [],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        $manager = $this->maxManagerAuth(maxUserId: 10_006);
        $secondManager = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_007,
                'first_name' => 'SecondManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );

        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Notify Place',
            'Burger',
            500,
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_050,
            firstName: 'NotifyCustomer',
        );

        $sentMessages = [];
        $messenger = $this->createMock(MaxMessengerClientInterface::class);
        $messenger
            ->expects($this->exactly(3))
            ->method('sendMessage')
            ->willReturnCallback(function (MaxMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $messenger->expects($this->never())->method('sendInlineKeyboardMessage');
        $this->app->instance(MaxMessengerClientInterface::class, $messenger);

        $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            1,
        )->assertOk();

        $this->patchJson('/api/food/admin/manual-orders/cart', [
            'max_user_id' => $customer->max_user_id,
            'delivery_address' => 'ул. Уведомлений, 1',
        ], $manager['headers'])->assertOk();

        $response = $this->postJson('/api/food/admin/manual-orders/submit', [
            'max_user_id' => $customer->max_user_id,
        ], $manager['headers']);

        $response->assertCreated();
        $orderId = (int) $response->json('order.id');

        $sentUserIds = array_map(
            static fn (MaxMessageDto $message): int => (int) $message->userId,
            $sentMessages,
        );
        $uniqueRecipientIds = array_values(array_unique($sentUserIds));
        sort($uniqueRecipientIds);

        $this->assertSame(
            [$manager['user']->max_user_id, $secondManager['user']->max_user_id],
            $uniqueRecipientIds,
        );
        $this->assertNotContains($customer->max_user_id, $sentUserIds);
        $this->assertSame(
            2,
            count(array_filter(
                $sentMessages,
                static fn (MaxMessageDto $message): bool => $message->text === sprintf(
                    'Заявка №%d принята к исполнению',
                    $orderId,
                ),
            )),
        );
        $this->assertTrue(
            collect($sentMessages)->contains(
                static fn (MaxMessageDto $message): bool => $message->userId === $manager['user']->max_user_id
                    && str_starts_with((string) $message->text, 'Заказ на '),
            ),
        );
    }

    /** Submit без адреса отклоняется. */
    public function test_submit_manual_order_rejects_missing_delivery_address(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('No Address Place', 'Tea', 100);
        $customer = MaxUser::query()->create([
            'max_user_id' => 55_060,
            'first_name' => 'NoAddress',
        ]);

        $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            1,
        )->assertOk();

        $this->postJson('/api/food/admin/manual-orders/submit', [
            'max_user_id' => $customer->max_user_id,
        ], $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите адрес доставки.');
    }

    /** Submit требует max_user_id. */
    public function test_submit_manual_order_requires_max_user_id(): void
    {
        $manager = $this->maxManagerAuth();

        $this->postJson('/api/food/admin/manual-orders/submit', [], $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_user_id']);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId = 10_006): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }

    /**
     * @param  array{headers: array<string, string>}  $manager
     */
    private function addManualCartItem(
        array $manager,
        int $customerMaxUserId,
        int $dishId,
        int $quantity,
    ): TestResponse {
        return $this->postJson('/api/food/admin/manual-orders/cart/items', [
            'max_user_id' => $customerMaxUserId,
            'dish_id' => $dishId,
            'quantity' => $quantity,
        ], $manager['headers']);
    }
}
