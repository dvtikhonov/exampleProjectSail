<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderRejectionScope;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminOrderReviewApiTest extends TestCase
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

    public function test_admin_me_returns_active_roles(): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->getJson('/api/food/admin/me', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('admin_roles', [FoodOrderAdminRole::AddressReviewer->value]);
    }

    public function test_address_admin_can_list_pending_orders(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->getJson('/api/food/admin/orders?scope=address&status=pending', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.address_review_status', OrderReviewStatus::Pending->value);
    }

    public function test_list_orders_returns_forbidden_without_role(): void
    {
        $this->createPendingReviewOrder();
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/orders?scope=address&status=pending', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_composition_admin_can_list_pending_orders_without_address_approval(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->getJson('/api/food/admin/orders?scope=composition&status=pending', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.composition_review_status', OrderReviewStatus::Pending->value);
    }

    public function test_composition_admin_can_list_legacy_orders_with_not_applicable_composition_status(): void
    {
        $orderId = $this->createPendingReviewOrder();
        FoodOrder::query()->whereKey($orderId)->update([
            'composition_review_status' => OrderReviewStatus::NotApplicable->value,
        ]);

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->getJson('/api/food/admin/orders?scope=composition&status=pending', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.composition_review_status', OrderReviewStatus::NotApplicable->value);

        $this->getJson("/api/food/admin/orders/{$orderId}?scope=composition", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId);

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Approved->value);
    }

    public function test_address_approve_keeps_order_in_review_until_composition_approved(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $compositionAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.address_review_status', OrderReviewStatus::Approved->value)
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Pending->value);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $orderId,
            'status' => OrderStatus::PendingReview->value,
            'address_reviewed_by' => 10_003,
            'address_review_status' => OrderReviewStatus::Approved->value,
            'composition_review_status' => OrderReviewStatus::Pending->value,
        ]);

        $this->getJson('/api/food/admin/orders?scope=composition&status=pending', $compositionAdmin['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId);
    }

    public function test_full_review_flow_confirms_order_and_notifies_customer(): void
    {
        $orderId = $this->createPendingReviewOrder(customerMaxUserId: 77_701);
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $compositionAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyConfirmed')
            ->with($this->callback(static fn (FoodOrder $order): bool => $order->id === $orderId));
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value);

        $this->postJson("/api/food/admin/orders/{$orderId}/payment/approve", [], $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.payment_review_status', OrderReviewStatus::Approved->value);

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $compositionAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Approved->value);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $orderId,
            'status' => OrderStatus::Confirmed->value,
            'composition_reviewed_by' => 10_004,
            'payment_reviewed_by' => 10_003,
        ]);
    }

    public function test_composition_approve_before_address_notifies_only_after_both_approved(): void
    {
        $orderId = $this->createPendingReviewOrder(customerMaxUserId: 77_702);
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $compositionAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyConfirmed')
            ->with($this->callback(static fn (FoodOrder $order): bool => $order->id === $orderId));
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $compositionAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Approved->value)
            ->assertJsonPath('order.address_review_status', OrderReviewStatus::Pending->value);

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.address_review_status', OrderReviewStatus::Approved->value);

        $this->postJson("/api/food/admin/orders/{$orderId}/payment/approve", [], $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('order.payment_review_status', OrderReviewStatus::Approved->value);
    }

    public function test_address_reject_requires_comment(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/reject", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_address_reject_with_comment_notifies_customer_and_marks_order_rejected(): void
    {
        $orderId = $this->createPendingReviewOrder(customerMaxUserId: 88_801);
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $comment = 'Адрес вне зоны доставки';

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyRejected')
            ->with(
                $this->callback(static fn (FoodOrder $order): bool => $order->id === $orderId),
                OrderRejectionScope::Address,
            );
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson("/api/food/admin/orders/{$orderId}/address/reject", [
            'comment' => $comment,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Rejected->value)
            ->assertJsonPath('order.address_rejection_comment', $comment);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $orderId,
            'status' => OrderStatus::Rejected->value,
            'address_rejection_comment' => $comment,
        ]);
    }

    public function test_composition_reject_requires_admin_role(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->authenticateMaxUser();

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/reject", [
            'comment' => 'Нет блюда',
        ], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_repeat_address_approve_returns_unprocessable(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $auth['headers'])
            ->assertOk();

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Address review already completed.');
    }

    public function test_address_approve_returns_forbidden_without_role(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->authenticateMaxUser();

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_composition_reject_requires_comment(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/reject", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_composition_reject_with_comment_notifies_customer_and_marks_order_rejected(): void
    {
        $orderId = $this->createPendingReviewOrder(customerMaxUserId: 88_802);
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );
        $comment = 'Блюдо временно недоступно';

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyRejected')
            ->with(
                $this->callback(static fn (FoodOrder $order): bool => $order->id === $orderId),
                OrderRejectionScope::Composition,
            );
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/reject", [
            'comment' => $comment,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Rejected->value)
            ->assertJsonPath('order.composition_rejection_comment', $comment);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $orderId,
            'status' => OrderStatus::Rejected->value,
            'composition_rejection_comment' => $comment,
        ]);
    }

    public function test_repeat_composition_approve_returns_unprocessable(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->mock(FoodOrderCustomerNotifierInterface::class)->shouldIgnoreMissing();

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $auth['headers'])
            ->assertOk();

        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Composition review already completed.');
    }

    public function test_show_order_detail_requires_scope_and_role(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->getJson("/api/food/admin/orders/{$orderId}", $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Query parameter scope is required.');

        $this->getJson("/api/food/admin/orders/{$orderId}?scope=address", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.delivery_address', 'ул. Примерная, 1');
    }

    public function test_admin_can_view_confirmed_order_by_id(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $compositionAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $addressAdmin['headers'])
            ->assertOk();
        $this->postJson("/api/food/admin/orders/{$orderId}/payment/approve", [], $addressAdmin['headers'])
            ->assertOk();
        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $compositionAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value);

        $this->getJson("/api/food/admin/orders/{$orderId}?scope=address", $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value);
    }

    public function test_admin_can_view_rejected_order_by_id(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/reject", [
            'comment' => 'Неверный адрес',
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Rejected->value);

        $this->getJson("/api/food/admin/orders/{$orderId}?scope=address", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.status', OrderStatus::Rejected->value)
            ->assertJsonPath('order.address_rejection_comment', 'Неверный адрес');
    }

    public function test_confirmed_order_not_in_pending_list_but_visible_with_status_all(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $compositionAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_004,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $addressAdmin['headers'])
            ->assertOk();
        $this->postJson("/api/food/admin/orders/{$orderId}/payment/approve", [], $addressAdmin['headers'])
            ->assertOk();
        $this->postJson("/api/food/admin/orders/{$orderId}/composition/approve", [], $compositionAdmin['headers'])
            ->assertOk();

        $this->getJson('/api/food/admin/orders?scope=address&status=pending', $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonCount(0, 'orders');

        $this->getJson('/api/food/admin/orders?scope=address&status=all', $addressAdmin['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.status', OrderStatus::Confirmed->value);
    }

    public function test_payment_reject_requires_comment(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->postJson("/api/food/admin/orders/{$orderId}/payment/reject", [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_payment_reject_with_comment_notifies_customer_and_marks_order_rejected(): void
    {
        $orderId = $this->createPendingReviewOrder(customerMaxUserId: 88_803);
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );
        $comment = 'Оплата не поступила';

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyRejected')
            ->with(
                $this->callback(static fn (FoodOrder $order): bool => $order->id === $orderId),
                OrderRejectionScope::Payment,
            );
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson("/api/food/admin/orders/{$orderId}/payment/reject", [
            'comment' => $comment,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Rejected->value)
            ->assertJsonPath('order.payment_rejection_comment', $comment);
    }

    public function test_address_admin_pending_list_includes_orders_awaiting_payment_only(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        FoodOrder::query()->whereKey($orderId)->update([
            'address_review_status' => OrderReviewStatus::Approved->value,
            'payment_review_status' => OrderReviewStatus::Pending->value,
        ]);

        $this->getJson('/api/food/admin/orders?scope=address&status=pending', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.payment_review_status', OrderReviewStatus::Pending->value);
    }

    public function test_list_orders_rejects_invalid_status(): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $this->getJson('/api/food/admin/orders?scope=address&status=unknown', $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid status. Use pending or all.');
    }

    private function createPendingReviewOrder(int $customerMaxUserId = 99_101): int
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Review Place',
            'Pasta',
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
