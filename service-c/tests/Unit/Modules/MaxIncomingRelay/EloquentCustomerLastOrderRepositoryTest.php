<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Repositories\EloquentCustomerLastOrderRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Unit: последний заказ пользователя по max_user_id (max_food_orders).
 */
final class EloquentCustomerLastOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Два заказа одного user → берётся новейший по created_at. */
    public function test_find_latest_by_max_user_id_returns_newest_order(): void
    {
        $older = $this->createOrder(54_321, '2026-09-10 10:00:00');
        $newer = $this->createOrder(54_321, '2026-09-20 15:30:00');
        $this->createOrder(99_999, '2026-09-25 12:00:00');

        $summary = $this->app->make(CustomerLastOrderRepositoryInterface::class)
            ->findLatestByMaxUserId(54_321);

        $this->assertNotNull($summary);
        $this->assertSame((int) $newer->id, $summary->id);
        $this->assertNotSame((int) $older->id, $summary->id);
        $this->assertSame(
            '20.09.2026',
            $summary->createdAt->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m.Y'),
        );
    }

    /** Нет заказов → null. */
    public function test_find_latest_returns_null_when_no_orders(): void
    {
        $this->assertNull(
            $this->app->make(EloquentCustomerLastOrderRepository::class)
                ->findLatestByMaxUserId(54_321),
        );
    }

    private function createOrder(int $maxUserId, string $createdAtMoscow): FoodOrder
    {
        MaxUser::query()->firstOrCreate(
            ['max_user_id' => $maxUserId],
            ['first_name' => 'RelayUser'.$maxUserId],
        );

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Relay Repo', 'Soup', 100);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тест, 1',
        ]);

        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Тест, 1',
        ]);

        $createdAt = new DateTimeImmutable($createdAtMoscow, new DateTimeZone('Europe/Moscow'));
        FoodOrder::query()->whereKey($order->id)->update([
            'created_at' => $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'updated_at' => $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);

        return $order->fresh();
    }
}
