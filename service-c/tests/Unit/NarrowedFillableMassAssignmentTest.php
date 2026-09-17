<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
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
 * Регрессия суженного $fillable: привилегированные поля не mass-assign через fill/create.
 */
class NarrowedFillableMassAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** ai_access_until вне fillable: fill()/create() не меняют колонку. */
    public function test_max_user_fill_ignores_ai_access_until(): void
    {
        $user = MaxUser::query()->create([
            'max_user_id' => 71_001,
            'first_name' => 'FillGuard',
            'ai_access_until' => '2026-09-16 12:00:00',
        ]);

        $this->assertNull($user->fresh()->ai_access_until);

        $user->fill(['ai_access_until' => '2026-09-16 15:00:00'])->save();

        $this->assertNull($user->fresh()->ai_access_until);

        $user->forceFill(['ai_access_until' => '2026-09-16 15:00:00'])->save();

        $this->assertNotNull($user->fresh()->ai_access_until);
    }

    /** Reviewer/rejection-поля вне fillable; update репозитория пишет их через forceFill. */
    public function test_food_order_fill_ignores_reviewer_fields_but_repository_update_persists_them(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 71_010,
            'first_name' => 'Customer',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 71_011,
            'first_name' => 'Reviewer',
        ]);
        $restaurant = Restaurant::factory()->create(['name' => 'Fill Guard Place']);
        $cart = Cart::query()->create([
            'max_user_id' => 71_010,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Fill, 1',
        ]);

        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => 71_010,
            'restaurant_id' => $restaurant->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Fill, 1',
            'address_reviewed_by' => 71_011,
            'address_rejection_comment' => 'should-be-ignored',
        ]);

        $this->assertNull($order->fresh()->address_reviewed_by);
        $this->assertNull($order->fresh()->address_rejection_comment);

        $order->fill([
            'address_reviewed_by' => 71_011,
            'address_rejection_comment' => 'via-fill',
        ])->save();

        $this->assertNull($order->fresh()->address_reviewed_by);
        $this->assertNull($order->fresh()->address_rejection_comment);

        $mapper = $this->app->make(FoodOrderMapper::class);
        $repository = $this->app->make(FoodOrderWriteRepositoryInterface::class);
        $record = $repository->update(
            $mapper->toRecord($order->fresh()),
            new FoodOrderUpdateCommand(
                addressReviewStatus: OrderReviewStatus::Approved,
                addressReviewedBy: 71_011,
                addressReviewedAt: '2026-09-16T12:00:00+00:00',
                addressRejectionComment: 'ok-via-forceFill',
            ),
        );

        $this->assertSame(OrderReviewStatus::Approved, $record->addressReviewStatus);
        $this->assertSame(71_011, $record->addressReviewedBy);
        $this->assertSame('ok-via-forceFill', $record->addressRejectionComment);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $order->id,
            'address_review_status' => OrderReviewStatus::Approved->value,
            'address_reviewed_by' => 71_011,
            'address_rejection_comment' => 'ok-via-forceFill',
        ]);
    }
}
