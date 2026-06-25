<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Services\Food\OrderStatusResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStatusResolverTest extends TestCase
{
    private OrderStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new OrderStatusResolver;
    }

    #[DataProvider('statusTransitionProvider')]
    public function test_resolve_returns_expected_order_status(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
        OrderReviewStatus $paymentReviewStatus,
        OrderStatus $expectedStatus,
    ): void {
        $this->assertSame(
            $expectedStatus,
            $this->resolver->resolve($addressReviewStatus, $compositionReviewStatus, $paymentReviewStatus),
        );
    }

    /**
     * @return array<string, array{OrderReviewStatus, OrderReviewStatus, OrderReviewStatus, OrderStatus}>
     */
    public static function statusTransitionProvider(): array
    {
        return [
            'pending address, pending composition, pending payment' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::Pending,
                OrderReviewStatus::Pending,
                OrderStatus::PendingReview,
            ],
            'pending address, not applicable composition, pending payment' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::NotApplicable,
                OrderReviewStatus::Pending,
                OrderStatus::PendingReview,
            ],
            'rejected address' => [
                OrderReviewStatus::Rejected,
                OrderReviewStatus::NotApplicable,
                OrderReviewStatus::Pending,
                OrderStatus::Rejected,
            ],
            'rejected payment' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Pending,
                OrderReviewStatus::Rejected,
                OrderStatus::Rejected,
            ],
            'approved address and payment, pending composition' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Pending,
                OrderReviewStatus::Approved,
                OrderStatus::PendingReview,
            ],
            'approved address, rejected composition' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Rejected,
                OrderReviewStatus::Approved,
                OrderStatus::Rejected,
            ],
            'pending address, approved composition and payment' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::Approved,
                OrderReviewStatus::Approved,
                OrderStatus::PendingReview,
            ],
            'fully approved' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Approved,
                OrderReviewStatus::Approved,
                OrderStatus::Confirmed,
            ],
        ];
    }
}
