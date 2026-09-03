<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Services\Food\Review\OrderCustomerNotifyRecipientResolver;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class OrderCustomerNotifyRecipientResolverTest extends TestCase
{
    /** Обычный заказ — получатель владелец заказа. */
    public function test_resolve_regular_order_returns_customer_max_user_id(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository->expects($this->never())->method('listActiveMaxUserIdsByRole');

        $resolver = new OrderCustomerNotifyRecipientResolver(
            $adminRepository,
            $this->loggerExpectingNoWarning(),
        );
        $order = $this->makeOrder(id: 10, maxUserId: 1002, isManual: false);

        $this->assertSame([1002], $resolver->resolveMaxUserIds($order));
    }

    /** Ручной заказ — получатели активные max_manager. */
    public function test_resolve_manual_order_returns_active_max_managers(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveMaxUserIdsByRole')
            ->with(FoodOrderAdminRole::MaxManager)
            ->willReturn([9001, 9002]);

        $resolver = new OrderCustomerNotifyRecipientResolver(
            $adminRepository,
            $this->loggerExpectingNoWarning(),
        );
        $order = $this->makeOrder(id: 11, maxUserId: 1002, isManual: true);

        $this->assertSame([9001, 9002], $resolver->resolveMaxUserIds($order));
    }

    /** Ручной заказ без менеджеров — пустой список и warning в лог. */
    public function test_resolve_manual_order_without_managers_logs_warning(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveMaxUserIdsByRole')
            ->with(FoodOrderAdminRole::MaxManager)
            ->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'MAX manual order customer notification: no active max_manager recipients',
                [
                    'order_id' => 12,
                    'max_user_id' => 1002,
                ],
            );

        $resolver = new OrderCustomerNotifyRecipientResolver($adminRepository, $logger);
        $order = $this->makeOrder(id: 12, maxUserId: 1002, isManual: true);

        $this->assertSame([], $resolver->resolveMaxUserIds($order));
    }

    /** @return MockObject&LoggerInterface */
    private function loggerExpectingNoWarning(): MockObject
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        return $logger;
    }

    /** Создаёт тестовую проекцию заказа. */
    private function makeOrder(int $id, int $maxUserId, bool $isManual): FoodOrderRecord
    {
        return new FoodOrderRecord(
            id: $id,
            cartId: null,
            maxUserId: $maxUserId,
            isManual: $isManual,
            createdByMaxUserId: null,
            restaurantId: 1,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: null,
            addressReviewedAt: null,
            compositionReviewedBy: null,
            compositionReviewedAt: null,
            addressRejectionComment: null,
            compositionRejectionComment: null,
            paymentReviewedBy: null,
            paymentReviewedAt: null,
            paymentRejectionComment: null,
            total: '0.00',
            deliveryAddress: null,
            deliveryDate: null,
            deliveryCost: null,
            itemsTotal: '0.00',
            itemsSnapshot: [],
            createdAt: now()->toIso8601String(),
            updatedAt: null,
        );
    }
}
