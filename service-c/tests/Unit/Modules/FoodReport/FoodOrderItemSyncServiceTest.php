<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FoodReport;

use App\Contracts\Food\Order\FoodOrderItemSyncServiceInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Modules\FoodReport\Contracts\FoodOrderConfirmedBackfillSourceInterface;
use App\Modules\FoodReport\Contracts\FoodOrderItemWriteRepositoryInterface;
use App\Modules\FoodReport\Models\FoodOrderItem;
use App\Modules\FoodReport\Services\FoodOrderItemSyncService;
use App\Repositories\Food\Order\FoodOrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Psr\Log\LoggerInterface;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Variant B: sync пишет items только для confirmed; иначе delete.
 */
class FoodOrderItemSyncServiceTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private FoodOrderItemSyncServiceInterface $syncService;

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
        $this->syncService = app(FoodOrderItemSyncServiceInterface::class);
    }

    /** pending_review → строк в max_food_order_items нет. */
    public function test_pending_order_leaves_items_empty(): void
    {
        $order = $this->createOrderModel(OrderStatus::PendingReview);

        $this->syncService->syncIfConfirmed($this->toRecord($order));

        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $order->id)->count());
    }

    /** confirmed → строки из items_snapshot с report_date = delivery_date. */
    public function test_confirmed_order_writes_items_from_snapshot(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-15',
            'items_snapshot' => [
                [
                    'dish_id' => 11,
                    'dish_name' => 'Борщ',
                    'unit_price' => '300.00',
                    'quantity' => 2,
                    'line_total' => '600.00',
                ],
                [
                    'dish_id' => 12,
                    'dish_name' => 'Салат',
                    'unit_price' => '150.00',
                    'quantity' => 1,
                    'line_total' => '150.00',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));

        $items = FoodOrderItem::query()->where('order_id', $order->id)->orderBy('dish_id')->get();
        $this->assertCount(2, $items);

        $this->assertSame($order->restaurant_id, $items[0]->restaurant_id);
        $this->assertSame('2026-09-15', $items[0]->report_date->format('Y-m-d'));
        $this->assertSame(11, $items[0]->dish_id);
        $this->assertSame('Борщ', $items[0]->dish_name);
        $this->assertSame('300.00', (string) $items[0]->unit_price);
        $this->assertSame(2, $items[0]->quantity);
        $this->assertSame('600.00', (string) $items[0]->line_total);

        $this->assertSame(12, $items[1]->dish_id);
        $this->assertSame('Салат', $items[1]->dish_name);
    }

    /** Две строки snapshot с одним dish_id (regular + combo) → одна row, qty/line_total = сумма. */
    public function test_confirmed_aggregates_snapshot_rows_by_dish_id(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-15',
            'items_snapshot' => [
                [
                    'dish_id' => 11,
                    'dish_name' => 'Борщ',
                    'unit_price' => '300.00',
                    'quantity' => 2,
                    'line_total' => '600.00',
                ],
                [
                    'dish_id' => 11,
                    'dish_name' => 'Борщ (комбо)',
                    'unit_price' => '250.00',
                    'quantity' => 1,
                    'line_total' => '250.00',
                    'combo_ref' => 'combo-1',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));

        $items = FoodOrderItem::query()->where('order_id', $order->id)->get();
        $this->assertCount(1, $items);
        $this->assertSame(11, $items[0]->dish_id);
        $this->assertSame(3, $items[0]->quantity);
        $this->assertSame('850.00', (string) $items[0]->line_total);
        $this->assertSame('Борщ (комбо)', $items[0]->dish_name);
        $this->assertSame('250.00', (string) $items[0]->unit_price);
    }

    /** confirmed без delivery_date → report_date из DATE(created_at). */
    public function test_confirmed_uses_created_at_when_delivery_date_null(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => null,
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'unit_price' => '100.00',
                    'quantity' => 1,
                    'line_total' => '100.00',
                ],
            ],
        ]);

        FoodOrder::query()->whereKey($order->id)->update([
            'created_at' => '2026-09-01 14:30:00',
        ]);
        $order->refresh();

        $this->syncService->syncIfConfirmed($this->toRecord($order));

        $item = FoodOrderItem::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('2026-09-01', $item->report_date->format('Y-m-d'));
    }

    /** confirmed с битым createdAt и пустым deliveryDate → исключение, строк нет. */
    public function test_confirmed_with_invalid_created_at_and_empty_delivery_throws(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => null,
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Суп',
                    'unit_price' => '100.00',
                    'quantity' => 1,
                    'line_total' => '100.00',
                ],
            ],
        ]);

        $record = $this->toRecord($order);
        $broken = new FoodOrderRecord(
            id: $record->id,
            cartId: $record->cartId,
            maxUserId: $record->maxUserId,
            isManual: $record->isManual,
            createdByMaxUserId: $record->createdByMaxUserId,
            restaurantId: $record->restaurantId,
            status: $record->status,
            addressReviewStatus: $record->addressReviewStatus,
            compositionReviewStatus: $record->compositionReviewStatus,
            paymentReviewStatus: $record->paymentReviewStatus,
            addressReviewedBy: $record->addressReviewedBy,
            addressReviewedAt: $record->addressReviewedAt,
            compositionReviewedBy: $record->compositionReviewedBy,
            compositionReviewedAt: $record->compositionReviewedAt,
            addressRejectionComment: $record->addressRejectionComment,
            compositionRejectionComment: $record->compositionRejectionComment,
            paymentReviewedBy: $record->paymentReviewedBy,
            paymentReviewedAt: $record->paymentReviewedAt,
            paymentRejectionComment: $record->paymentRejectionComment,
            total: $record->total,
            deliveryAddress: $record->deliveryAddress,
            deliveryDate: null,
            deliveryCost: $record->deliveryCost,
            itemsTotal: $record->itemsTotal,
            itemsSnapshot: $record->itemsSnapshot,
            createdAt: 'not-a-valid-date',
            updatedAt: $record->updatedAt,
        );

        try {
            $this->syncService->syncIfConfirmed($broken);
            $this->fail('Ожидался FoodDomainException при невалидном createdAt.');
        } catch (FoodDomainException $e) {
            $this->assertStringContainsString('невалидный createdAt', $e->getMessage());
            $this->assertStringContainsString((string) $order->id, $e->getMessage());
        }

        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $order->id)->count());
    }

    /** Повторный sync с тем же dish_id атомарно заменяет строку (поля обновляются). */
    public function test_confirmed_composition_update_replaces_same_dish(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-10',
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Старое',
                    'unit_price' => '10.00',
                    'quantity' => 1,
                    'line_total' => '10.00',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));
        $this->assertSame(1, FoodOrderItem::query()->where('order_id', $order->id)->count());

        $order->items_snapshot = [
            [
                'dish_id' => 1,
                'dish_name' => 'Обновлённое',
                'unit_price' => '50.00',
                'quantity' => 3,
                'line_total' => '150.00',
            ],
        ];
        $order->save();

        $this->syncService->syncIfConfirmed($this->toRecord($order->refresh()));

        $items = FoodOrderItem::query()->where('order_id', $order->id)->get();
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]->dish_id);
        $this->assertSame('Обновлённое', $items[0]->dish_name);
        $this->assertSame(3, $items[0]->quantity);
        $this->assertSame('150.00', (string) $items[0]->line_total);
    }

    /** Строки с dish_id=null заменяются атомарно (без дублей по имени). */
    public function test_confirmed_null_dish_id_replace_is_atomic(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-10',
            'items_snapshot' => [
                [
                    'dish_id' => null,
                    'dish_name' => 'Кастом',
                    'unit_price' => '10.00',
                    'quantity' => 1,
                    'line_total' => '10.00',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));
        $this->assertSame(1, FoodOrderItem::query()->where('order_id', $order->id)->whereNull('dish_id')->count());

        $order->items_snapshot = [
            [
                'dish_id' => null,
                'dish_name' => 'Кастом',
                'unit_price' => '25.00',
                'quantity' => 2,
                'line_total' => '50.00',
            ],
            [
                'dish_id' => null,
                'dish_name' => 'Другое',
                'unit_price' => '5.00',
                'quantity' => 1,
                'line_total' => '5.00',
            ],
        ];
        $order->save();

        $this->syncService->syncIfConfirmed($this->toRecord($order->refresh()));

        $items = FoodOrderItem::query()
            ->where('order_id', $order->id)
            ->whereNull('dish_id')
            ->orderBy('dish_name')
            ->get();
        $this->assertCount(2, $items);
        $this->assertSame('Другое', $items[0]->dish_name);
        $this->assertSame('5.00', (string) $items[0]->line_total);
        $this->assertSame('Кастом', $items[1]->dish_name);
        $this->assertSame(2, $items[1]->quantity);
        $this->assertSame('50.00', (string) $items[1]->line_total);
    }

    /** Пустой dish_name / quantity<=0 пропускаются с warning и order_id. */
    public function test_confirmed_skips_invalid_snapshot_rows_with_warning(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-10',
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Ок',
                    'unit_price' => '10.00',
                    'quantity' => 1,
                    'line_total' => '10.00',
                ],
                [
                    'dish_id' => 2,
                    'dish_name' => '   ',
                    'unit_price' => '20.00',
                    'quantity' => 1,
                    'line_total' => '20.00',
                ],
                [
                    'dish_id' => 3,
                    'dish_name' => 'Ноль',
                    'unit_price' => '30.00',
                    'quantity' => 0,
                    'line_total' => '0.00',
                ],
            ],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->with(
                $this->stringContains('Пропуск строки items_snapshot'),
                $this->callback(static function (array $context) use ($order): bool {
                    return ($context['order_id'] ?? null) === $order->id;
                }),
            );

        $sync = new FoodOrderItemSyncService(
            app(FoodOrderItemWriteRepositoryInterface::class),
            app(FoodMoneyFormatterInterface::class),
            $logger,
        );

        $sync->syncIfConfirmed($this->toRecord($order));

        $items = FoodOrderItem::query()->where('order_id', $order->id)->get();
        $this->assertCount(1, $items);
        $this->assertSame('Ок', $items[0]->dish_name);
    }

    /** Смена dish_id удаляет orphan и upsert-ит новую строку. */
    public function test_confirmed_composition_change_dish_removes_orphan(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-10',
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Старое',
                    'unit_price' => '10.00',
                    'quantity' => 1,
                    'line_total' => '10.00',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));

        $order->items_snapshot = [
            [
                'dish_id' => 2,
                'dish_name' => 'Новое',
                'unit_price' => '50.00',
                'quantity' => 3,
                'line_total' => '150.00',
            ],
        ];
        $order->save();

        $this->syncService->syncIfConfirmed($this->toRecord($order->refresh()));

        $items = FoodOrderItem::query()->where('order_id', $order->id)->get();
        $this->assertCount(1, $items);
        $this->assertSame(2, $items[0]->dish_id);
        $this->assertSame('Новое', $items[0]->dish_name);
        $this->assertSame(3, $items[0]->quantity);
        $this->assertSame('150.00', (string) $items[0]->line_total);
    }

    /** rejected → items удаляются. */
    public function test_rejected_order_deletes_items(): void
    {
        $order = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-10',
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Блюдо',
                    'unit_price' => '100.00',
                    'quantity' => 1,
                    'line_total' => '100.00',
                ],
            ],
        ]);

        $this->syncService->syncIfConfirmed($this->toRecord($order));
        $this->assertSame(1, FoodOrderItem::query()->where('order_id', $order->id)->count());

        $order->status = OrderStatus::Rejected;
        $order->save();

        $this->syncService->syncIfConfirmed($this->toRecord($order->refresh()));

        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $order->id)->count());
    }

    /** Backfill обрабатывает только confirmed. */
    public function test_backfill_command_syncs_only_confirmed_orders(): void
    {
        $pending = $this->createOrderModel(OrderStatus::PendingReview, [
            'items_snapshot' => [
                [
                    'dish_id' => 1,
                    'dish_name' => 'Pending',
                    'unit_price' => '10.00',
                    'quantity' => 1,
                    'line_total' => '10.00',
                ],
            ],
        ]);
        $confirmed = $this->createOrderModel(OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-12',
            'items_snapshot' => [
                [
                    'dish_id' => 2,
                    'dish_name' => 'Confirmed',
                    'unit_price' => '20.00',
                    'quantity' => 2,
                    'line_total' => '40.00',
                ],
            ],
        ]);
        $rejected = $this->createOrderModel(OrderStatus::Rejected, [
            'items_snapshot' => [
                [
                    'dish_id' => 3,
                    'dish_name' => 'Rejected',
                    'unit_price' => '30.00',
                    'quantity' => 1,
                    'line_total' => '30.00',
                ],
            ],
        ]);

        $exitCode = Artisan::call('food-report:backfill-order-items');

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $pending->id)->count());
        $this->assertSame(0, FoodOrderItem::query()->where('order_id', $rejected->id)->count());
        $this->assertSame(1, FoodOrderItem::query()->where('order_id', $confirmed->id)->count());
        $this->assertSame('Confirmed', FoodOrderItem::query()->where('order_id', $confirmed->id)->value('dish_name'));

        $exitCode = Artisan::call('food-report:backfill-order-items');

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, FoodOrderItem::query()->where('order_id', $confirmed->id)->count());
        $this->assertSame('Confirmed', FoodOrderItem::query()->where('order_id', $confirmed->id)->value('dish_name'));
    }

    /** Источник backfill возвращает только confirmed. */
    public function test_backfill_source_yields_only_confirmed(): void
    {
        $this->createOrderModel(OrderStatus::PendingReview);
        $confirmed = $this->createOrderModel(OrderStatus::Confirmed);
        $this->createOrderModel(OrderStatus::Rejected);

        $ids = [];
        $count = app(FoodOrderConfirmedBackfillSourceInterface::class)->eachConfirmed(
            static function (FoodOrderRecord $order) use (&$ids): void {
                $ids[] = $order->id;
            },
        );

        $this->assertSame(1, $count);
        $this->assertSame([$confirmed->id], $ids);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrderModel(OrderStatus $status, array $overrides = []): FoodOrder
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 91_000 + FoodOrder::query()->count(),
            'first_name' => 'SyncUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Sync Restaurant',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Sync, 1',
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
            'delivery_address' => 'ул. Sync, 1',
            'delivery_date' => '2026-09-10',
        ], $overrides));
    }

    private function toRecord(FoodOrder $model): FoodOrderRecord
    {
        return app(FoodOrderMapper::class)->toRecord($model);
    }
}
