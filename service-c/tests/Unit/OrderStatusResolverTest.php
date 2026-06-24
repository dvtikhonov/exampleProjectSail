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

        $this->resolver = new OrderStatusResolver();
    }

    #[DataProvider('statusTransitionProvider')]
    public function test_resolve_returns_expected_order_status(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
        OrderStatus $expectedStatus,
    ): void {
        $this->assertSame(
            $expectedStatus,
            $this->resolver->resolve($addressReviewStatus, $compositionReviewStatus),
        );
    }

    /**
     * @return array<string, array{OrderReviewStatus, OrderReviewStatus, OrderStatus}>
     */
    public static function statusTransitionProvider(): array
    {
        return [
            'pending address, pending composition' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::Pending,
                OrderStatus::PendingReview,
            ],
            'pending address, not applicable composition' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::NotApplicable,
                OrderStatus::PendingReview,
            ],
            'rejected address' => [
                OrderReviewStatus::Rejected,
                OrderReviewStatus::NotApplicable,
                OrderStatus::Rejected,
            ],
            'approved address, pending composition' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Pending,
                OrderStatus::PendingReview,
            ],
            'approved address, rejected composition' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Rejected,
                OrderStatus::Rejected,
            ],
            'pending address, approved composition' => [
                OrderReviewStatus::Pending,
                OrderReviewStatus::Approved,
                OrderStatus::PendingReview,
            ],
            'fully approved' => [
                OrderReviewStatus::Approved,
                OrderReviewStatus::Approved,
                OrderStatus::Confirmed,
            ],
        ];
    }
}
