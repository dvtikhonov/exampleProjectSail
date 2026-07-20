<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderRejectionScope;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Models\Dish;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Models\Restaurant;
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

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mock(FoodOrderMaxNotifierInterface::class)->shouldIgnoreMissing();
    }

    /** Admin/me возвращает активные роли. */
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

    /** Адресный админ может получить список ожидающих заказов. */
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

    /** Список заказов возвращает 403 без роли. */
    public function test_list_orders_returns_forbidden_without_role(): void
    {
        $this->createPendingReviewOrder();
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/orders?scope=address&status=pending', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Админ состава может видеть ожидающие заказы без одобрения адреса. */
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

    /** Админ состава может видеть legacy-заказы со статусом состава not_applicable. */
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

    /** Одобрение адреса оставляет заказ на проверке, пока состав не одобрен. */
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

    /** Полный цикл проверки подтверждает заказ и уведомляет клиента. */
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

    /** Одобрение состава до адреса уведомляет только после обоих одобрений. */
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

    /** Отклонение адреса требует комментарий. */
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

    /** Отклонение адреса с комментарием уведомляет клиента и помечает заказ отклонённым. */
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

    /** Отклонение состава требует роль админа. */
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

    /** Повторное одобрение адреса возвращает 422. */
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

    /** Одобрение адреса возвращает 403 без роли. */
    public function test_address_approve_returns_forbidden_without_role(): void
    {
        $orderId = $this->createPendingReviewOrder();
        $auth = $this->authenticateMaxUser();

        $this->postJson("/api/food/admin/orders/{$orderId}/address/approve", [], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Отклонение состава требует комментарий. */
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

    /** Отклонение состава с комментарием уведомляет клиента и помечает заказ отклонённым. */
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

    /** Повторное одобрение состава возвращает 422. */
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

    /** Просмотр деталей заказа требует scope и роль. */
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

    /** Админ может видеть подтверждённый заказ по id. */
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

    /** Админ может видеть отклонённый заказ по id. */
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

    /** Подтверждённый заказ нет в списке ожидания, но виден при status=all. */
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

    /** Отклонение оплаты требует комментарий. */
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

    /** Отклонение оплаты с комментарием уведомляет клиента и помечает заказ отклонённым. */
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

    /** Список ожидания адресного админа включает только заказы, ожидающие оплаты. */
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

    /** Список заказов отклоняет невалидный статус. */
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

    /** Обновление состава меняет количество и пересчитывает суммы, включая доставку. */
    public function test_composition_update_changes_quantity_and_recalculates_totals(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_801);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 4],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.id', $fixture['order_id'])
            ->assertJsonPath('order.items_snapshot.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('order.items_snapshot.0.quantity', 4)
            ->assertJsonPath('order.items_total', '1200.00')
            ->assertJsonPath('order.delivery_cost', '0.00')
            ->assertJsonPath('order.total', '1200.00')
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Pending->value);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $fixture['order_id'],
            'items_total' => '1200.00',
            'delivery_cost' => '0.00',
            'total' => '1200.00',
        ]);
    }

    /** Обновление состава может удалить позицию, оставив остальные. */
    public function test_composition_update_can_remove_item(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_802);
        $extraDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Salad',
            'price' => 150,
        ]);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 1],
                ['dish_id' => $extraDish->id, 'quantity' => 2],
            ],
        ], $auth['headers'])->assertOk();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 1],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'order.items_snapshot')
            ->assertJsonPath('order.items_snapshot.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('order.items_total', '300.00')
            ->assertJsonPath('order.delivery_cost', '200.00')
            ->assertJsonPath('order.total', '500.00');
    }

    /** Обновление состава может добавить обычное блюдо из меню ресторана. */
    public function test_composition_update_can_add_regular_dish(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_803);
        $extraDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Soup',
            'price' => 250,
        ]);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 1],
                ['dish_id' => $extraDish->id, 'quantity' => 2],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'order.items_snapshot')
            ->assertJsonPath('order.items_snapshot.1.dish_id', $extraDish->id)
            ->assertJsonPath('order.items_snapshot.1.dish_name', 'Soup')
            ->assertJsonPath('order.items_snapshot.1.quantity', 2)
            ->assertJsonPath('order.items_total', '800.00')
            ->assertJsonPath('order.delivery_cost', '200.00')
            ->assertJsonPath('order.total', '1000.00');
    }

    /** Обновление состава может добавить комбо и пересчитать суммы. */
    public function test_composition_update_can_add_combo_and_recalculates_totals(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_804);
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
        $comboRef = '550e8400-e29b-41d4-a716-446655440000';
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 2,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $sideDish->id,
                ],
                [
                    'dish_id' => $sideDish->id,
                    'quantity' => 2,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $fixture['dish']->id,
                ],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonCount(2, 'order.items_snapshot')
            ->assertJsonPath('order.items_snapshot.0.combo_ref', $comboRef)
            ->assertJsonPath('order.items_snapshot.0.combo_partner_dish_ids.0', $sideDish->id)
            ->assertJsonPath('order.items_snapshot.1.combo_ref', $comboRef)
            ->assertJsonPath('order.items_snapshot.1.combo_partner_dish_ids.0', $fixture['dish']->id)
            ->assertJsonPath('order.items_total', '960.00')
            ->assertJsonPath('order.delivery_cost', '200.00')
            ->assertJsonPath('order.total', '1160.00');
    }

    /** После успешного обновления состава вызывается notifyCompositionChanged. */
    public function test_composition_update_notifies_customer(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_805);
        $auth = $this->compositionAdminAuth();

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyCompositionChanged')
            ->with($this->callback(
                static fn (FoodOrder $order): bool => $order->id === $fixture['order_id']
                    && (float) $order->items_total === 600.0,
            ));
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 2],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.items_total', '600.00');
    }

    /** Обновление состава требует роль composition_reviewer. */
    public function test_composition_update_requires_admin_role(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_806);
        $auth = $this->authenticateMaxUser();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 1],
            ],
        ], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Обновление состава недоступно после завершения проверки состава. */
    public function test_composition_update_returns_unprocessable_when_not_pending(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_807);
        $auth = $this->compositionAdminAuth();

        $this->mock(FoodOrderCustomerNotifierInterface::class)->shouldIgnoreMissing();

        $this->postJson(
            "/api/food/admin/orders/{$fixture['order_id']}/composition/approve",
            [],
            $auth['headers'],
        )->assertOk();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 2],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Composition review already completed.');
    }

    /** Обновление состава отклоняет пустой список позиций. */
    public function test_composition_update_rejects_empty_items(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_808);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    /** Обновление состава отклоняет битую пару комбо. */
    public function test_composition_update_rejects_broken_combo_pair(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_809);
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
        $comboRef = '550e8400-e29b-41d4-a716-446655440000';
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $sideDish->id,
                ],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                sprintf('Combo pair "%s" must contain exactly two items.', $comboRef),
            );
    }

    /** Обновление состава отклоняет блюдо другого ресторана. */
    public function test_composition_update_rejects_dish_from_another_restaurant(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_810);
        $other = FoodTestDataBuilder::createRestaurantWithDish('Other Place', 'Burger', 400);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $other['dish']->id, 'quantity' => 1],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Dish does not belong to the order restaurant.');
    }

    /** Можно менять количество уже принятого в заказ блюда, даже если оно стало недоступным. */
    public function test_composition_update_allows_quantity_change_when_existing_dish_unavailable(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_812);
        $auth = $this->compositionAdminAuth();

        $fixture['dish']->update(['is_available' => false]);

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 3],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.items_snapshot.0.quantity', 3)
            ->assertJsonPath('order.items_total', '900.00')
            ->assertJsonPath('order.total', '1100.00');
    }

    /** Новое блюдо с is_available=false при правке состава отклоняется. */
    public function test_composition_update_rejects_newly_added_unavailable_dish(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_813);
        $unavailableDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Unavailable Soup',
            'price' => 250,
            'is_available' => false,
        ]);
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 1],
                ['dish_id' => $unavailableDish->id, 'quantity' => 1],
            ],
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Dish is not available.');
    }

    /** Можно менять количество комбо, уже лежащего в заказе, если блюда стали недоступны. */
    public function test_composition_update_allows_combo_quantity_change_when_dishes_unavailable(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_814);
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
        $comboRef = '550e8400-e29b-41d4-a716-446655440001';
        $auth = $this->compositionAdminAuth();

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $sideDish->id,
                ],
                [
                    'dish_id' => $sideDish->id,
                    'quantity' => 1,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $fixture['dish']->id,
                ],
            ],
        ], $auth['headers'])->assertOk();

        $fixture['dish']->update(['is_available' => false]);
        $sideDish->update(['is_available' => false]);

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                [
                    'dish_id' => $fixture['dish']->id,
                    'quantity' => 20,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $sideDish->id,
                ],
                [
                    'dish_id' => $sideDish->id,
                    'quantity' => 20,
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_id' => $fixture['dish']->id,
                ],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('order.items_snapshot.0.quantity', 20)
            ->assertJsonPath('order.items_snapshot.1.quantity', 20)
            ->assertJsonPath('order.items_total', '9600.00');
    }

    /** После правки состава approve по-прежнему работает. */
    public function test_composition_approve_after_edit_still_works(): void
    {
        $fixture = $this->createPendingReviewOrderFixture(customerMaxUserId: 77_811);
        $compositionAdmin = $this->compositionAdminAuth();
        $addressAdmin = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_003,
                'first_name' => 'AddressAdmin',
            ])),
            FoodOrderAdminRole::AddressReviewer,
        );

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyCompositionChanged')
            ->with($this->callback(static fn (FoodOrder $order): bool => $order->id === $fixture['order_id']));
        $customerNotifier
            ->expects($this->once())
            ->method('notifyConfirmed')
            ->with($this->callback(static fn (FoodOrder $order): bool => $order->id === $fixture['order_id']));
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->putJson("/api/food/admin/orders/{$fixture['order_id']}/composition", [
            'items' => [
                ['dish_id' => $fixture['dish']->id, 'quantity' => 2],
            ],
        ], $compositionAdmin['headers'])
            ->assertOk()
            ->assertJsonPath('order.items_total', '600.00');

        $this->postJson(
            "/api/food/admin/orders/{$fixture['order_id']}/address/approve",
            [],
            $addressAdmin['headers'],
        )->assertOk();
        $this->postJson(
            "/api/food/admin/orders/{$fixture['order_id']}/payment/approve",
            [],
            $addressAdmin['headers'],
        )->assertOk();
        $this->postJson(
            "/api/food/admin/orders/{$fixture['order_id']}/composition/approve",
            [],
            $compositionAdmin['headers'],
        )
            ->assertOk()
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('order.composition_review_status', OrderReviewStatus::Approved->value)
            ->assertJsonPath('order.items_total', '600.00');
    }

    /** Создаёт заказ в статусе ожидания проверки. */
    private function createPendingReviewOrder(int $customerMaxUserId = 99_101): int
    {
        return $this->createPendingReviewOrderFixture($customerMaxUserId)['order_id'];
    }

    /**
     * Создаёт заказ в статусе ожидания проверки и возвращает связанные сущности.
     *
     * @return array{
     *     order_id: int,
     *     dish: Dish,
     *     restaurant: Restaurant,
     *     category: MenuCategory,
     * }
     */
    private function createPendingReviewOrderFixture(int $customerMaxUserId = 99_101): array
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

        return [
            'order_id' => (int) $response->json('order.id'),
            'dish' => $fixture['dish'],
            'restaurant' => $fixture['restaurant'],
            'category' => $fixture['category'],
        ];
    }

    /**
     * @return array{user: MaxUser, token: string, headers: array<string, string>}
     */
    private function compositionAdminAuth(int $maxUserId = 10_004): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'CompositionAdmin',
            ])),
            FoodOrderAdminRole::CompositionReviewer,
        );
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
