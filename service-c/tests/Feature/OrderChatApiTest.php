<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderMessageAuthorType;
use App\Enums\Food\OrderReviewStatus;
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

class OrderChatApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mock(FoodOrderMaxNotifierInterface::class)->shouldIgnoreMissing();
    }

    public function test_customer_can_list_messages_on_own_order(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_101);
        $auth = $this->authenticateMaxUser(MaxUser::query()->where('max_user_id', 55_101)->firstOrFail());

        $this->getJson("/api/food/orders/{$orderId}/messages", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    public function test_customer_can_send_message_on_own_order(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_102);
        $auth = $this->authenticateMaxUser(MaxUser::query()->where('max_user_id', 55_102)->firstOrFail());
        $body = 'Уточните подъезд, пожалуйста';

        $response = $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => $body,
        ], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('message.food_order_id', $orderId)
            ->assertJsonPath('message.sender_max_user_id', 55_102)
            ->assertJsonPath('message.author_type', OrderMessageAuthorType::Customer->value)
            ->assertJsonPath('message.body', $body)
            ->assertJsonPath('message.sender.first_name', 'FoodTester');

        $this->assertDatabaseHas('max_food_order_messages', [
            'food_order_id' => $orderId,
            'sender_max_user_id' => 55_102,
            'body' => $body,
        ]);
    }

    public function test_customer_cannot_access_another_users_order_chat(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_201);
        $otherAuth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 55_202,
            'first_name' => 'OtherUser',
        ]));

        $this->getJson("/api/food/orders/{$orderId}/messages", $otherAuth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => 'Чужое сообщение',
        ], $otherAuth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_admin_can_read_and_write_messages_on_any_order(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_301);
        $adminAuth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $adminReply = 'Принято, уточняем доставку';

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => $adminReply,
        ], $adminAuth['headers'])
            ->assertCreated()
            ->assertJsonPath('message.author_type', OrderMessageAuthorType::Admin->value)
            ->assertJsonPath('message.body', $adminReply);

        $this->getJson("/api/food/orders/{$orderId}/messages", $adminAuth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', $adminReply)
            ->assertJsonPath('messages.0.author_type', OrderMessageAuthorType::Admin->value);
    }

    public function test_send_message_validates_body(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_401);
        $auth = $this->authenticateMaxUser(MaxUser::query()->where('max_user_id', 55_401)->firstOrFail());

        $this->postJson("/api/food/orders/{$orderId}/messages", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => '   ',
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => str_repeat('а', 2001),
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_list_messages_supports_after_id_pagination_in_order(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_501);
        $customer = MaxUser::query()->where('max_user_id', 55_501)->firstOrFail();
        $auth = $this->authenticateMaxUser($customer);

        $first = FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Первое сообщение',
        ]);
        $second = FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Второе сообщение',
        ]);
        $third = FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Третье сообщение',
        ]);

        $this->getJson("/api/food/orders/{$orderId}/messages", $auth['headers'])
            ->assertOk()
            ->assertJsonCount(3, 'messages')
            ->assertJsonPath('messages.0.id', $first->id)
            ->assertJsonPath('messages.1.id', $second->id)
            ->assertJsonPath('messages.2.id', $third->id);

        $this->getJson("/api/food/orders/{$orderId}/messages?after_id={$first->id}", $auth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('messages.1.id', $third->id);

        $this->getJson("/api/food/orders/{$orderId}/messages?after_id={$second->id}&limit=1", $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $third->id);
    }

    public function test_list_messages_marks_incoming_messages_as_read(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_801);
        $customer = MaxUser::query()->where('max_user_id', 55_801)->firstOrFail();
        $customerAuth = $this->authenticateMaxUser($customer);
        $admin = MaxUser::query()->create([
            'max_user_id' => 10_801,
            'first_name' => 'ChatAdmin',
        ]);
        $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($admin),
            FoodOrderAdminRole::AddressReviewer,
        );

        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $admin->max_user_id,
            'body' => 'Уточните адрес',
        ]);
        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $admin->max_user_id,
            'body' => 'Ждём ответа',
        ]);

        $this->getJson('/api/food/orders', $customerAuth['headers'])
            ->assertOk()
            ->assertJsonPath('orders.0.unread_count', 2);

        $this->getJson("/api/food/orders/{$orderId}/messages", $customerAuth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'messages');

        $this->getJson('/api/food/orders', $customerAuth['headers'])
            ->assertOk()
            ->assertJsonPath('orders.0.unread_count', 0);

        FoodOrderMessage::query()->create([
            'food_order_id' => $orderId,
            'sender_max_user_id' => $admin->max_user_id,
            'body' => 'Новое сообщение',
        ]);

        $this->getJson('/api/food/orders', $customerAuth['headers'])
            ->assertOk()
            ->assertJsonPath('orders.0.unread_count', 1);
    }

    public function test_chat_remains_available_for_confirmed_and_rejected_orders(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_601);
        $customerAuth = $this->authenticateMaxUser(MaxUser::query()->where('max_user_id', 55_601)->firstOrFail());

        FoodOrder::query()->whereKey($orderId)->update([
            'status' => OrderStatus::Confirmed->value,
            'address_review_status' => OrderReviewStatus::Approved->value,
            'composition_review_status' => OrderReviewStatus::Approved->value,
        ]);

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => 'Спасибо, жду доставку',
        ], $customerAuth['headers'])
            ->assertCreated();

        FoodOrder::query()->whereKey($orderId)->update([
            'status' => OrderStatus::Rejected->value,
        ]);

        $this->postJson("/api/food/orders/{$orderId}/messages", [
            'body' => 'Можно уточнить причину отказа?',
        ], $customerAuth['headers'])
            ->assertCreated();

        $this->getJson("/api/food/orders/{$orderId}/messages", $customerAuth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'messages');
    }

    public function test_list_messages_returns_not_found_for_missing_order(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/orders/999999/messages', $auth['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Order not found.');
    }

    public function test_non_admin_non_owner_cannot_access_chat(): void
    {
        $orderId = $this->createSubmittedOrder(customerMaxUserId: 55_701);
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 55_702,
            'first_name' => 'Stranger',
        ]));

        $this->getJson("/api/food/orders/{$orderId}/messages", $auth['headers'])
            ->assertForbidden();
    }

    private function createSubmittedOrder(int $customerMaxUserId = 99_101): int
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Chat Place',
            'Soup',
            300,
        );
        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory(
                $fixture['customer_category'],
                $customerMaxUserId,
            ),
        );

        $this->addItemToCart($auth, $fixture['dish']->id, 1);
        $this->setCartDeliveryAddress($auth);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value);

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
