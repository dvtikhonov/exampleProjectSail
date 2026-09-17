<?php

declare(strict_types=1);

namespace Tests\Feature\FoodReport;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Modules\FoodReport\Contracts\FoodOrderItemSyncServiceInterface;
use App\Modules\FoodReport\Models\FoodOrderItem;
use App\Repositories\Food\Order\FoodOrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: GET /api/food/admin/reports/{revenue|top-dishes} — только confirmed.
 */
class AdminFoodReportQueryApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('max_food_order_items')) {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_09_10_000001_create_max_food_order_items_table.php',
            ]);
        }

        Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_09_11_000001_add_unique_order_dish_to_max_food_order_items_table.php',
        ]);

        $this->resetFoodDomainTables();
    }

    /** Revenue требует аутентификацию. */
    public function test_revenue_requires_authentication(): void
    {
        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString())
            ->assertUnauthorized();
    }

    /** Top-dishes требует аутентификацию. */
    public function test_top_dishes_requires_authentication(): void
    {
        $this->getJson('/api/food/admin/reports/top-dishes?'.$this->queryString())
            ->assertUnauthorized();
    }

    /** Без роли max_manager — 403. */
    public function test_reports_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'restaurant_id' => Restaurant::factory()->create()->id,
        ]), $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** Чуждая роль menu_manager — 403. */
    public function test_reports_forbidden_with_menu_manager_role(): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 20_001,
                'first_name' => 'MenuManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );
        $restaurant = Restaurant::factory()->create();

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'restaurant_id' => $restaurant->id,
        ]), $auth['headers'])
            ->assertForbidden();
    }

    /** Валидация: обязательные поля и формат дат. */
    public function test_revenue_validation_requires_filter_fields(): void
    {
        $manager = $this->maxManagerAuth();

        $this->getJson('/api/food/admin/reports/revenue', $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from', 'date_to', 'restaurant_id']);
    }

    /** Валидация: период не длиннее 93 дней. */
    public function test_revenue_rejects_span_over_93_days(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create();

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'date_from' => '2026-01-01',
            'date_to' => '2026-04-05',
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    /** Выручка считает только confirmed и items_total; pending/rejected/draft не влияют. */
    public function test_revenue_includes_only_confirmed_orders_items_total(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Report Cafe']);

        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '1000.00',
            'total' => '1100.00',
            'delivery_cost' => '100.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '500.00',
            'total' => '550.00',
            'delivery_cost' => '50.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::PendingReview, [
            'delivery_date' => '2026-09-01',
            'items_total' => '9999.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Rejected, [
            'delivery_date' => '2026-09-01',
            'items_total' => '8888.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::DraftAfterScanning, [
            'delivery_date' => '2026-09-01',
            'items_total' => '7777.00',
            'is_manual' => true,
        ]);

        $otherRestaurant = Restaurant::factory()->create(['name' => 'Other']);
        $this->createOrder($otherRestaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '3000.00',
        ]);

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-01',
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-01')
            ->assertJsonPath('days.0.orders_count', 2)
            ->assertJsonPath('days.0.amount', '1500.00')
            ->assertJsonPath('days.0.average_check', '750.00')
            ->assertJsonPath('meta.orders_count', 2)
            ->assertJsonPath('meta.amount', '1500.00')
            ->assertJsonPath('meta.average_check', '750.00');
    }

    /** Выручка: дни в JSON идут по дате по возрастанию. */
    public function test_revenue_days_ordered_by_date_ascending(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Asc Cafe']);

        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-03',
            'items_total' => '300.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '100.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-02',
            'items_total' => '200.00',
        ]);

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-03',
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-01')
            ->assertJsonPath('days.1.date', '2026-09-02')
            ->assertJsonPath('days.2.date', '2026-09-03')
            ->assertJsonPath('meta.orders_count', 3)
            ->assertJsonPath('meta.amount', '600.00');
    }

    /** Топ блюд строится из items после sync; неподтверждённые заказы не влияют. */
    public function test_top_dishes_from_synced_items_only_confirmed(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Top Cafe']);
        $sync = app(FoodOrderItemSyncServiceInterface::class);
        $mapper = app(FoodOrderMapper::class);

        $confirmed = $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-02',
            'items_total' => '750.00',
            'items_snapshot' => [
                [
                    'dish_id' => 5,
                    'dish_name' => 'Борщ',
                    'unit_price' => '300.00',
                    'quantity' => 2,
                    'line_total' => '600.00',
                ],
                [
                    'dish_id' => 6,
                    'dish_name' => 'Салат',
                    'unit_price' => '150.00',
                    'quantity' => 1,
                    'line_total' => '150.00',
                ],
            ],
        ]);
        $sync->syncIfConfirmed($mapper->toRecord($confirmed));

        $pending = $this->createOrder($restaurant, OrderStatus::PendingReview, [
            'delivery_date' => '2026-09-02',
            'items_total' => '500.00',
            'items_snapshot' => [
                [
                    'dish_id' => 99,
                    'dish_name' => 'Не должно попасть',
                    'unit_price' => '500.00',
                    'quantity' => 10,
                    'line_total' => '5000.00',
                ],
            ],
        ]);
        $sync->syncIfConfirmed($mapper->toRecord($pending));

        // Защита на чтении: даже «осиротевшие» items неподтверждённого заказа отсекаются join'ом.
        FoodOrderItem::query()->create([
            'order_id' => $pending->id,
            'restaurant_id' => $restaurant->id,
            'report_date' => '2026-09-02',
            'dish_id' => 99,
            'dish_name' => 'Утечка pending',
            'unit_price' => '500.00',
            'quantity' => 10,
            'line_total' => '5000.00',
        ]);

        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $pending->id)->where('dish_name', 'Не должно попасть')->count());

        $this->getJson('/api/food/admin/reports/top-dishes?'.$this->queryString([
            'date_from' => '2026-09-02',
            'date_to' => '2026-09-02',
            'restaurant_id' => $restaurant->id,
            'limit' => 20,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-02')
            ->assertJsonPath('days.0.items.0.dish_id', 5)
            ->assertJsonPath('days.0.items.0.dish_name', 'Борщ')
            ->assertJsonPath('days.0.items.0.quantity', 2)
            ->assertJsonPath('days.0.items.0.amount', '600.00')
            ->assertJsonPath('days.0.items.1.dish_name', 'Салат')
            ->assertJsonMissing(['dish_name' => 'Утечка pending'])
            ->assertJsonMissing(['dish_name' => 'Не должно попасть']);
    }

    /** Rejected / draft не попадают в топ даже при наличии строк items. */
    public function test_top_dishes_excludes_rejected_and_draft_via_status_join(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create();

        $rejected = $this->createOrder($restaurant, OrderStatus::Rejected, [
            'delivery_date' => '2026-09-03',
            'items_total' => '100.00',
        ]);
        FoodOrderItem::query()->create([
            'order_id' => $rejected->id,
            'restaurant_id' => $restaurant->id,
            'report_date' => '2026-09-03',
            'dish_id' => 1,
            'dish_name' => 'Rejected Dish',
            'unit_price' => '100.00',
            'quantity' => 5,
            'line_total' => '500.00',
        ]);

        $draft = $this->createOrder($restaurant, OrderStatus::DraftAfterScanning, [
            'delivery_date' => '2026-09-03',
            'items_total' => '50.00',
            'is_manual' => true,
        ]);
        FoodOrderItem::query()->create([
            'order_id' => $draft->id,
            'restaurant_id' => $restaurant->id,
            'report_date' => '2026-09-03',
            'dish_id' => 2,
            'dish_name' => 'Draft Dish',
            'unit_price' => '50.00',
            'quantity' => 3,
            'line_total' => '150.00',
        ]);

        $this->getJson('/api/food/admin/reports/top-dishes?'.$this->queryString([
            'date_from' => '2026-09-03',
            'date_to' => '2026-09-03',
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days', [])
            ->assertJsonMissing(['dish_name' => 'Rejected Dish'])
            ->assertJsonMissing(['dish_name' => 'Draft Dish']);
    }

    /** limit ограничивает top-N на день на стороне SQL (не весь GROUP BY в PHP). */
    public function test_top_dishes_respects_per_day_limit(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Limit Cafe']);
        $confirmed = $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-04',
            'items_total' => '600.00',
        ]);

        foreach ([
            ['dish_id' => 1, 'dish_name' => 'A', 'quantity' => 10, 'line_total' => '100.00'],
            ['dish_id' => 2, 'dish_name' => 'B', 'quantity' => 9, 'line_total' => '90.00'],
            ['dish_id' => 3, 'dish_name' => 'C', 'quantity' => 8, 'line_total' => '80.00'],
            ['dish_id' => 4, 'dish_name' => 'D', 'quantity' => 1, 'line_total' => '10.00'],
        ] as $row) {
            FoodOrderItem::query()->create([
                'order_id' => $confirmed->id,
                'restaurant_id' => $restaurant->id,
                'report_date' => '2026-09-04',
                'dish_id' => $row['dish_id'],
                'dish_name' => $row['dish_name'],
                'unit_price' => '10.00',
                'quantity' => $row['quantity'],
                'line_total' => $row['line_total'],
            ]);
        }

        $response = $this->getJson('/api/food/admin/reports/top-dishes?'.$this->queryString([
            'date_from' => '2026-09-04',
            'date_to' => '2026-09-04',
            'restaurant_id' => $restaurant->id,
            'limit' => 2,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-04')
            ->assertJsonPath('days.0.items.0.dish_name', 'A')
            ->assertJsonPath('days.0.items.1.dish_name', 'B')
            ->assertJsonMissing(['dish_name' => 'C'])
            ->assertJsonMissing(['dish_name' => 'D']);

        $this->assertCount(2, $response->json('days.0.items'));
    }

    /** Пустой период возвращает пустые days и нулевую meta. */
    public function test_revenue_empty_period_returns_zero_meta(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create();

        $this->getJson('/api/food/admin/reports/revenue?'.$this->queryString([
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-07',
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertOk()
            ->assertJsonPath('days', [])
            ->assertJsonPath('meta.orders_count', 0)
            ->assertJsonPath('meta.amount', '0.00')
            ->assertJsonPath('meta.average_check', '0.00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(Restaurant $restaurant, OrderStatus $status, array $overrides = []): FoodOrder
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 80_000 + FoodOrder::query()->count(),
            'first_name' => 'ReportUser',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Отчёт, 1',
        ]);

        return FoodOrder::query()->create(array_merge([
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
            'delivery_address' => 'ул. Отчёт, 1',
            'delivery_date' => '2026-09-01',
            'is_manual' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    private function queryString(array $params = []): string
    {
        return http_build_query(array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-10',
            'restaurant_id' => 1,
        ], $params));
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId = 20_010): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
