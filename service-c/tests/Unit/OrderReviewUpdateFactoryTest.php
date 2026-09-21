<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Review\OrderReviewStep;
use App\Services\Food\Review\OrderReviewUpdateFactory;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Review\UpdateStep\AddressOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\CompositionOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\PaymentOrderReviewUpdateStepHandler;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit-тесты OrderReviewUpdateFactory: делегирование в step-handlers без match.
 */
class OrderReviewUpdateFactoryTest extends TestCase
{
    private OrderReviewUpdateFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-31T12:00:00+00:00'));
        $resolver = new OrderStatusResolver;

        $this->factory = new OrderReviewUpdateFactory($clock, [
            OrderReviewStep::Address->value => new AddressOrderReviewUpdateStepHandler($resolver),
            OrderReviewStep::Composition->value => new CompositionOrderReviewUpdateStepHandler($resolver),
            OrderReviewStep::Payment->value => new PaymentOrderReviewUpdateStepHandler($resolver),
        ]);
    }

    public function test_build_approval_update_for_address_sets_address_fields(): void
    {
        $order = $this->makeOrder(
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Approved,
            paymentReviewStatus: OrderReviewStatus::Approved,
        );

        $command = $this->factory->buildApprovalUpdate(OrderReviewStep::Address, $order, 7);

        $this->assertSame(OrderStatus::Confirmed, $command->status);
        $this->assertSame(OrderReviewStatus::Approved, $command->addressReviewStatus);
        $this->assertSame(7, $command->addressReviewedBy);
        $this->assertSame('2026-08-31T12:00:00+00:00', $command->addressReviewedAt);
        $this->assertNull($command->compositionReviewStatus);
        $this->assertNull($command->paymentReviewStatus);
    }

    public function test_build_rejection_update_for_payment_sets_comment_and_rejected_status(): void
    {
        $order = $this->makeOrder(
            addressReviewStatus: OrderReviewStatus::Approved,
            compositionReviewStatus: OrderReviewStatus::Approved,
            paymentReviewStatus: OrderReviewStatus::Pending,
        );

        $command = $this->factory->buildRejectionUpdate(
            OrderReviewStep::Payment,
            $order,
            9,
            'Нет оплаты',
        );

        $this->assertSame(OrderStatus::Rejected, $command->status);
        $this->assertSame(OrderReviewStatus::Rejected, $command->paymentReviewStatus);
        $this->assertSame(9, $command->paymentReviewedBy);
        $this->assertSame('Нет оплаты', $command->paymentRejectionComment);
        $this->assertNull($command->addressReviewStatus);
    }

    public function test_build_approval_update_for_composition_keeps_pending_when_others_pending(): void
    {
        $order = $this->makeOrder(
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
        );

        $command = $this->factory->buildApprovalUpdate(OrderReviewStep::Composition, $order, 3);

        $this->assertSame(OrderStatus::PendingReview, $command->status);
        $this->assertSame(OrderReviewStatus::Approved, $command->compositionReviewStatus);
        $this->assertSame(3, $command->compositionReviewedBy);
    }

    public function test_missing_handler_throws_invalid_argument_exception(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-31T12:00:00+00:00'));
        $factory = new OrderReviewUpdateFactory($clock, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No OrderReviewUpdateStepHandler registered for step: address');

        $factory->buildApprovalUpdate(
            OrderReviewStep::Address,
            $this->makeOrder(
                addressReviewStatus: OrderReviewStatus::Pending,
                compositionReviewStatus: OrderReviewStatus::Pending,
                paymentReviewStatus: OrderReviewStatus::Pending,
            ),
            1,
        );
    }

    private function makeOrder(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
        OrderReviewStatus $paymentReviewStatus,
    ): FoodOrderRecord {
        return new FoodOrderRecord(
            id: 1,
            cartId: 1,
            maxUserId: 1002,
            isManual: false,
            createdByMaxUserId: null,
            restaurantId: 1,
            status: OrderStatus::PendingReview,
            addressReviewStatus: $addressReviewStatus,
            compositionReviewStatus: $compositionReviewStatus,
            paymentReviewStatus: $paymentReviewStatus,
            addressReviewedBy: null,
            addressReviewedAt: null,
            compositionReviewedBy: null,
            compositionReviewedAt: null,
            addressRejectionComment: null,
            compositionRejectionComment: null,
            paymentReviewedBy: null,
            paymentReviewedAt: null,
            paymentRejectionComment: null,
            total: '100.00',
            deliveryAddress: 'ул. Тест, 1',
            deliveryDate: null,
            deliveryCost: null,
            itemsTotal: '100.00',
            itemsSnapshot: [],
            createdAt: '2026-08-31T10:00:00+00:00',
            updatedAt: null,
        );
    }
}
