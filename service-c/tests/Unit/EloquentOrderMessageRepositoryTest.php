<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\FoodOrderMessage;
use App\Models\Max\MaxUser;
use App\Repositories\Food\Chat\EloquentOrderMessageRepository;
use App\Repositories\Food\Chat\OrderMessageMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Регресс: getChatStatsForOrders без SQLi через users/$name.
 */
class EloquentOrderMessageRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private EloquentOrderMessageRepository $repository;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->repository = new EloquentOrderMessageRepository(new OrderMessageMapper);
    }

    /**
     * getChatStatsForOrders считает unread/last_message_at без SQL к users
     * и без интерполяции пользовательского ввода в DB::select.
     */
    public function test_get_chat_stats_for_orders_avoids_users_table_and_string_interpolation(): void
    {
        $viewer = MaxUser::query()->create([
            'max_user_id' => 77_101,
            'first_name' => 'Viewer',
        ]);
        $sender = MaxUser::query()->create([
            'max_user_id' => 77_102,
            'first_name' => 'Sender',
        ]);

        $orderWithUnread = $this->createOrder($viewer->max_user_id);
        $orderEmpty = $this->createOrder($viewer->max_user_id);

        FoodOrderMessage::query()->create([
            'food_order_id' => $orderWithUnread->id,
            'sender_max_user_id' => $sender->max_user_id,
            'body' => 'Новое сообщение',
        ]);

        /** @var list<string> $sqlLog */
        $sqlLog = [];
        DB::listen(static function ($query) use (&$sqlLog): void {
            $sqlLog[] = $query->sql;
        });

        $stats = $this->repository->getChatStatsForOrders(
            [$orderWithUnread->id, $orderEmpty->id],
            $viewer->max_user_id,
        );

        $this->assertSame(1, $stats[$orderWithUnread->id]['unread_count']);
        $this->assertNotNull($stats[$orderWithUnread->id]['last_message_at']);
        $this->assertSame(0, $stats[$orderEmpty->id]['unread_count']);
        $this->assertNull($stats[$orderEmpty->id]['last_message_at']);

        $this->assertNotEmpty($sqlLog, 'Expected SQL activity while computing chat stats.');

        foreach ($sqlLog as $sql) {
            $normalized = strtolower($sql);

            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:from|join|update|into)\s+[`"]?users[`"]?\b/',
                $normalized,
                'getChatStatsForOrders must not query the users table (legacy SQLi vector).',
            );

            // Значения — через bindings (?), не конкатенация в SQL-строку.
            $this->assertStringNotContainsString(
                (string) $viewer->max_user_id,
                $sql,
                'viewer id must be bound, not interpolated into SQL.',
            );
            $this->assertStringNotContainsString(
                (string) $orderWithUnread->id,
                $sql,
                'order id must be bound, not interpolated into SQL.',
            );
        }
    }

    /** Пустой список заказов возвращает пустой массив без SQL. */
    public function test_get_chat_stats_for_orders_returns_empty_for_empty_ids(): void
    {
        $this->assertSame([], $this->repository->getChatStatsForOrders([], 1));
    }

    private function createOrder(int $maxUserId): FoodOrder
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Chat Stats Repo', 'Soup', 150);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тест, 1',
        ]);

        return FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '150.00',
            'items_total' => '150.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Тест, 1',
        ]);
    }
}
