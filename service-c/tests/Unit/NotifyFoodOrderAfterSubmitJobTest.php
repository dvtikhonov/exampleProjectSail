<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;
use App\Mappers\Max\MaxUserDisplayMapper;
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
 * Unit-тесты job уведомлений после оформления заказа.
 */
class NotifyFoodOrderAfterSubmitJobTest extends TestCase
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
        $job = new NotifyFoodOrderAfterSubmitJob(
            orderDto: new OrderDto(
                id: 1,
                status: OrderStatus::PendingReview->value,
                restaurantId: 1,
                restaurantName: 'Test',
                itemsTotal: '0.00',
                deliveryApplicable: false,
                deliveryCost: null,
                total: '0.00',
                deliveryAddress: null,
                deliveryDate: null,
                itemsSnapshot: [],
                createdAt: now()->toIso8601String(),
            ),
            orderId: 1,
            maxUserId: 1,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    /** Job вызывает UI Stand notifier и notifySubmitted. */
    public function test_handle_notifies_max_and_customer_submitted(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 88_001,
            'first_name' => 'JobUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Job Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тест, 1',
        ]);
        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Тест, 1',
        ]);

        $dto = new OrderDto(
            id: $order->id,
            status: OrderStatus::PendingReview->value,
            restaurantId: $restaurant->id,
            restaurantName: 'Job Place',
            itemsTotal: '100.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '100.00',
            deliveryAddress: 'ул. Тест, 1',
            deliveryDate: null,
            itemsSnapshot: [],
            createdAt: now()->toIso8601String(),
        );

        $maxNotifier = $this->createMock(FoodOrderMaxNotifierInterface::class);
        $maxNotifier->expects($this->once())->method('notify')->with(
            $this->callback(fn (OrderDto $o): bool => $o->id === $order->id),
            $this->callback(fn (MaxUserDisplayDto $u): bool => $u->maxUserId === $maxUser->max_user_id),
        );

        $customerNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifySubmitted')
            ->with($this->callback(fn (FoodOrderRecord $o): bool => $o->id === $order->id));
        $customerNotifier->expects($this->never())->method('notifyConfirmed');

        $job = new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUser->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );

        $cache = $this->app->make(CacheStoreInterface::class);

        $job->handle(
            $maxNotifier,
            $customerNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            app(MaxUserIdentityRepositoryInterface::class),
            app(MaxUserDisplayMapper::class),
            $cache,
            app(LoggerInterface::class),
        );
    }

    /** Повторный handle не вызывает notifier'ы — маркеры идемпотентности в кэше. */
    public function test_repeated_handle_skips_notifiers_when_markers_present(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 88_002,
            'first_name' => 'RetryUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Retry Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Повтор, 2',
        ]);
        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '200.00',
            'items_total' => '200.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Повтор, 2',
        ]);

        $dto = new OrderDto(
            id: $order->id,
            status: OrderStatus::PendingReview->value,
            restaurantId: $restaurant->id,
            restaurantName: 'Retry Place',
            itemsTotal: '200.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '200.00',
            deliveryAddress: 'ул. Повтор, 2',
            deliveryDate: null,
            itemsSnapshot: [],
            createdAt: now()->toIso8601String(),
        );

        $maxNotifier = $this->createMock(FoodOrderMaxNotifierInterface::class);
        $maxNotifier->expects($this->once())->method('notify');

        $customerNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $customerNotifier->expects($this->once())->method('notifySubmitted');

        $job = new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUser->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );

        $deps = [
            $maxNotifier,
            $customerNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            app(MaxUserIdentityRepositoryInterface::class),
            app(MaxUserDisplayMapper::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        ];

        $job->handle(...$deps);
        $job->handle(...$deps);
    }

    /** При сбое UI Stand маркер не ставится — retry доотправляет только эту ногу. */
    public function test_handle_retries_failed_ui_stand_leg_without_marker(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 88_003,
            'first_name' => 'FailUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Fail Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Сбой, 3',
        ]);
        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '300.00',
            'items_total' => '300.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Сбой, 3',
        ]);

        $dto = new OrderDto(
            id: $order->id,
            status: OrderStatus::PendingReview->value,
            restaurantId: $restaurant->id,
            restaurantName: 'Fail Place',
            itemsTotal: '300.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '300.00',
            deliveryAddress: 'ул. Сбой, 3',
            deliveryDate: null,
            itemsSnapshot: [],
            createdAt: now()->toIso8601String(),
        );

        $maxNotifier = $this->createMock(FoodOrderMaxNotifierInterface::class);
        $maxNotifyAttempts = 0;
        $maxNotifier
            ->expects($this->exactly(2))
            ->method('notify')
            ->willReturnCallback(function () use (&$maxNotifyAttempts): void {
                $maxNotifyAttempts++;
                if ($maxNotifyAttempts === 1) {
                    throw new RuntimeException('MAX UI Stand unavailable');
                }
            });

        $customerNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $customerNotifier->expects($this->once())->method('notifySubmitted');

        $job = new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUser->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );

        $deps = [
            $maxNotifier,
            $customerNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            app(MaxUserIdentityRepositoryInterface::class),
            app(MaxUserDisplayMapper::class),
            $this->app->make(CacheStoreInterface::class),
            app(LoggerInterface::class),
        ];

        try {
            $job->handle(...$deps);
            $this->fail('Expected RuntimeException on first handle');
        } catch (RuntimeException) {
            // первая попытка: UI Stand упал, customer не вызывался
        }

        $job->handle(...$deps);
    }

    /** Если UI Stand уже доставлен, retry отправляет только customer-ногу. */
    public function test_handle_skips_ui_stand_when_marker_present(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 88_004,
            'first_name' => 'PartialUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Partial Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Частично, 4',
        ]);
        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '400.00',
            'items_total' => '400.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Частично, 4',
        ]);

        $dto = new OrderDto(
            id: $order->id,
            status: OrderStatus::PendingReview->value,
            restaurantId: $restaurant->id,
            restaurantName: 'Partial Place',
            itemsTotal: '400.00',
            deliveryApplicable: false,
            deliveryCost: null,
            total: '400.00',
            deliveryAddress: 'ул. Частично, 4',
            deliveryDate: null,
            itemsSnapshot: [],
            createdAt: now()->toIso8601String(),
        );

        $cache = $this->app->make(CacheStoreInterface::class);
        $cache->forever('food.notify.'.$order->id.'.ui_stand', 1);

        $maxNotifier = $this->createMock(FoodOrderMaxNotifierInterface::class);
        $maxNotifier->expects($this->never())->method('notify');

        $customerNotifier = $this->createMock(FoodOrderStatusNotifierInterface::class);
        $customerNotifier->expects($this->once())->method('notifySubmitted');

        $job = new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUser->max_user_id,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );

        $job->handle(
            $maxNotifier,
            $customerNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            app(MaxUserIdentityRepositoryInterface::class),
            app(MaxUserDisplayMapper::class),
            $cache,
            app(LoggerInterface::class),
        );
    }
}
