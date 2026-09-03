<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
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

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
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

        $job->handle(
            $maxNotifier,
            $customerNotifier,
            app(FoodOrderCustomerReadRepositoryInterface::class),
            app(MaxUserRepositoryInterface::class),
            app(MaxUserDisplayMapper::class),
            app(LoggerInterface::class),
        );
    }
}
