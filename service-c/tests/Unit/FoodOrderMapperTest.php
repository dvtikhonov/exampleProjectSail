<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Repositories\Food\Order\FoodOrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Unit-тесты маппера Eloquent FoodOrder ↔ Domain Record/Command.
 */
class FoodOrderMapperTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private FoodOrderMapper $mapper;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mapper = new FoodOrderMapper;
    }

    /** toRecord сохраняет ключевые поля модели заказа. */
    public function test_to_record_maps_key_fields_from_model(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 10_003,
            'first_name' => 'Reviewer',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 9_001,
            'first_name' => 'Creator',
        ]);

        $itemsSnapshot = [
            ['dish_id' => 1, 'dish_name' => 'Soup', 'quantity' => 2, 'price' => '250.00'],
        ];

        $model = $this->createOrderModel([
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Approved,
            'payment_reviewed_by' => 10_003,
            'payment_reviewed_at' => '2026-08-31 12:00:00',
            'total' => '550.00',
            'items_total' => '500.00',
            'delivery_cost' => '50.00',
            'delivery_address' => 'ул. Тестовая, 1',
            'delivery_date' => '2026-09-01',
            'items_snapshot' => $itemsSnapshot,
            'is_manual' => true,
            'created_by_max_user_id' => 9_001,
        ]);

        $record = $this->mapper->toRecord($model->load(['restaurant', 'maxUser']));

        $this->assertSame($model->id, $record->id);
        $this->assertSame($model->cart_id, $record->cartId);
        $this->assertSame((int) $model->max_user_id, $record->maxUserId);
        $this->assertTrue($record->isManual);
        $this->assertSame(9_001, $record->createdByMaxUserId);
        $this->assertSame((int) $model->restaurant_id, $record->restaurantId);
        $this->assertSame(OrderStatus::PendingReview, $record->status);
        $this->assertSame(OrderReviewStatus::Pending, $record->addressReviewStatus);
        $this->assertSame(OrderReviewStatus::Pending, $record->compositionReviewStatus);
        $this->assertSame(OrderReviewStatus::Approved, $record->paymentReviewStatus);
        $this->assertSame(10_003, $record->paymentReviewedBy);
        $this->assertNotNull($record->paymentReviewedAt);
        $this->assertSame('550.00', $record->total);
        $this->assertSame('500.00', $record->itemsTotal);
        $this->assertSame('50.00', $record->deliveryCost);
        $this->assertSame('ул. Тестовая, 1', $record->deliveryAddress);
        $this->assertSame('2026-09-01', $record->deliveryDate);
        $this->assertEquals($itemsSnapshot, $record->itemsSnapshot);
        $this->assertSame('Mapper Place', $record->restaurantName);
        $this->assertSame('MapperUser', $record->customerFirstName);
    }

    /** CreateCommand → attributes → model → Record сохраняет ключевые поля. */
    public function test_create_command_round_trip_preserves_key_fields(): void
    {
        $fixture = $this->createFixture();

        $command = new FoodOrderCreateCommand(
            cartId: $fixture['cart']->id,
            maxUserId: $fixture['maxUser']->max_user_id,
            isManual: false,
            createdByMaxUserId: null,
            restaurantId: $fixture['restaurant']->id,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: null,
            addressReviewedAt: null,
            compositionReviewedBy: null,
            compositionReviewedAt: null,
            paymentReviewedBy: null,
            paymentReviewedAt: null,
            total: '300.00',
            deliveryAddress: 'ул. RoundTrip, 7',
            deliveryDate: '2026-09-02',
            deliveryCost: '30.00',
            itemsTotal: '270.00',
            itemsSnapshot: [
                ['dish_id' => 5, 'dish_name' => 'Salad', 'quantity' => 1, 'price' => '270.00'],
            ],
        );

        $model = FoodOrder::query()->create($this->mapper->toCreateAttributes($command));
        $record = $this->mapper->toRecord($model->fresh());

        $this->assertSame($command->cartId, $record->cartId);
        $this->assertSame($command->maxUserId, $record->maxUserId);
        $this->assertFalse($record->isManual);
        $this->assertNull($record->createdByMaxUserId);
        $this->assertSame($command->restaurantId, $record->restaurantId);
        $this->assertSame(OrderStatus::PendingReview, $record->status);
        $this->assertSame(OrderReviewStatus::Pending, $record->addressReviewStatus);
        $this->assertSame(OrderReviewStatus::Pending, $record->compositionReviewStatus);
        $this->assertSame(OrderReviewStatus::Pending, $record->paymentReviewStatus);
        $this->assertSame('300.00', $record->total);
        $this->assertSame('270.00', $record->itemsTotal);
        $this->assertSame('30.00', $record->deliveryCost);
        $this->assertSame('ул. RoundTrip, 7', $record->deliveryAddress);
        $this->assertSame('2026-09-02', $record->deliveryDate);
        $this->assertEquals($command->itemsSnapshot, $record->itemsSnapshot);
    }

    /** toUpdateAttributes отдаёт только заданные поля команды. */
    public function test_to_update_attributes_maps_only_set_fields(): void
    {
        $command = new FoodOrderUpdateCommand(
            status: OrderStatus::Confirmed,
            addressReviewStatus: OrderReviewStatus::Approved,
            addressReviewedBy: 10_003,
            addressReviewedAt: '2026-08-31T15:00:00+00:00',
            itemsTotal: '400.00',
            total: '450.00',
        );

        $attributes = $this->mapper->toUpdateAttributes($command);

        $this->assertSame([
            'status' => OrderStatus::Confirmed,
            'address_review_status' => OrderReviewStatus::Approved,
            'address_reviewed_by' => 10_003,
            'address_reviewed_at' => '2026-08-31T15:00:00+00:00',
            'items_total' => '400.00',
            'total' => '450.00',
        ], $attributes);
        $this->assertArrayNotHasKey('composition_review_status', $attributes);
        $this->assertArrayNotHasKey('payment_review_status', $attributes);
        $this->assertArrayNotHasKey('items_snapshot', $attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrderModel(array $overrides = []): FoodOrder
    {
        $fixture = $this->createFixture();

        return FoodOrder::query()->create(array_merge([
            'cart_id' => $fixture['cart']->id,
            'max_user_id' => $fixture['maxUser']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Default, 1',
        ], $overrides));
    }

    /**
     * @return array{maxUser: MaxUser, restaurant: Restaurant, cart: Cart}
     */
    private function createFixture(): array
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 88_100,
            'first_name' => 'MapperUser',
        ]);
        $restaurant = Restaurant::factory()->create([
            'name' => 'Mapper Place',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Default, 1',
        ]);

        return [
            'maxUser' => $maxUser,
            'restaurant' => $restaurant,
            'cart' => $cart,
        ];
    }
}
