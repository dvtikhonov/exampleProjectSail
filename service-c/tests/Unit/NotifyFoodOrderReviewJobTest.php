<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderReviewNotifyKind;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Jobs\Food\NotifyFoodOrderReviewJob;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Unit-тесты job уведомлений после review / правки состава.
 */
class NotifyFoodOrderReviewJobTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Job явно задаёт tries, timeout и backoff для ретраев. */
    public function test_job_defines_tries_timeout_and_backoff(): void
    {
        $job = new NotifyFoodOrderReviewJob(
            orderId: 1,
            kind: FoodOrderReviewNotifyKind::Approved,
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    /** Job вызывает notifyConfirmed для Approved. */
    public function test_handle_notifies_customer_approved(): void
    {
        $order = $this->createOrder(maxUserId: 88_101, status: OrderStatus::Confirmed);

        $statusNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $statusNotifier
            ->expects($this->once())
            ->method('notifyConfirmed')
            ->with($this->callback(fn (FoodOrderRecord $o): bool => $o->id === $order->id));
        $statusNotifier->expects($this->never())->method('notifyRejected');

        $compositionNotifier = $this->createMock(FoodOrderCompositionNotifierInterface::class);
        $compositionNotifier->expects($this->never())->method('notifyCompositionChanged');

        $job = new NotifyFoodOrderReviewJob(
            orderId: $order->id,
            kind: FoodOrderReviewNotifyKind::Approved,
        );

        $job->handle(
            $statusNotifier,
            $compositionNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        );
    }

    /** Job вызывает notifyRejected для Rejected. */
    public function test_handle_notifies_customer_rejected(): void
    {
        $order = $this->createOrder(maxUserId: 88_102, status: OrderStatus::Rejected);

        $statusNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $statusNotifier
            ->expects($this->once())
            ->method('notifyRejected')
            ->with(
                $this->callback(fn (FoodOrderRecord $o): bool => $o->id === $order->id),
                OrderRejectionScope::Address,
            );

        $compositionNotifier = $this->createMock(FoodOrderCompositionNotifierInterface::class);
        $compositionNotifier->expects($this->never())->method('notifyCompositionChanged');

        $job = new NotifyFoodOrderReviewJob(
            orderId: $order->id,
            kind: FoodOrderReviewNotifyKind::Rejected,
            rejectionScope: OrderRejectionScope::Address,
        );

        $job->handle(
            $statusNotifier,
            $compositionNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        );
    }

    /** Job вызывает notifyCompositionChanged. */
    public function test_handle_notifies_customer_composition_changed(): void
    {
        $order = $this->createOrder(maxUserId: 88_103, status: OrderStatus::PendingReview);

        $statusNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $statusNotifier->expects($this->never())->method('notifyConfirmed');
        $statusNotifier->expects($this->never())->method('notifyRejected');

        $compositionNotifier = $this->createMock(FoodOrderCompositionNotifierInterface::class);
        $compositionNotifier
            ->expects($this->once())
            ->method('notifyCompositionChanged')
            ->with($this->callback(fn (FoodOrderRecord $o): bool => $o->id === $order->id));

        $job = new NotifyFoodOrderReviewJob(
            orderId: $order->id,
            kind: FoodOrderReviewNotifyKind::CompositionChanged,
            idempotencySuffix: '2026-09-16T12:00:00+00:00',
        );

        $job->handle(
            $statusNotifier,
            $compositionNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        );
    }

    /** Повторный handle не вызывает notifier — маркер идемпотентности в кэше. */
    public function test_repeated_handle_skips_notifier_when_marker_present(): void
    {
        $order = $this->createOrder(maxUserId: 88_104, status: OrderStatus::Confirmed);

        $statusNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $statusNotifier->expects($this->once())->method('notifyConfirmed');

        $compositionNotifier = $this->createMock(FoodOrderCompositionNotifierInterface::class);
        $compositionNotifier->expects($this->never())->method('notifyCompositionChanged');

        $job = new NotifyFoodOrderReviewJob(
            orderId: $order->id,
            kind: FoodOrderReviewNotifyKind::Approved,
        );

        $deps = [
            $statusNotifier,
            $compositionNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        ];

        $job->handle(...$deps);
        $job->handle(...$deps);
    }

    /** При сбое маркер не ставится — retry доотправляет уведомление. */
    public function test_handle_retries_without_marker_after_failure(): void
    {
        $order = $this->createOrder(maxUserId: 88_105, status: OrderStatus::Confirmed);

        $attempts = 0;
        $statusNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $statusNotifier
            ->expects($this->exactly(2))
            ->method('notifyConfirmed')
            ->willReturnCallback(function () use (&$attempts): void {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('MAX unavailable');
                }
            });

        $compositionNotifier = $this->createMock(FoodOrderCompositionNotifierInterface::class);
        $compositionNotifier->expects($this->never())->method('notifyCompositionChanged');

        $job = new NotifyFoodOrderReviewJob(
            orderId: $order->id,
            kind: FoodOrderReviewNotifyKind::Approved,
        );

        $deps = [
            $statusNotifier,
            $compositionNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        ];

        try {
            $job->handle(...$deps);
            $this->fail('Expected RuntimeException on first handle');
        } catch (RuntimeException) {
            // первая попытка упала, маркер не поставлен
        }

        $job->handle(...$deps);
    }

    private function createOrder(int $maxUserId, OrderStatus $status): FoodOrder
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => 'ReviewJobUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Review Job Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тест, 1',
        ]);

        return FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => $status,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Тест, 1',
        ]);
    }
}
