<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrder;
use App\Services\Food\OrderCustomerNotifyRecipientResolver;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class OrderCustomerNotifyRecipientResolverTest extends TestCase
{
    /** Обычный заказ — получатель владелец заказа. */
    public function test_resolve_regular_order_returns_customer_max_user_id(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository->expects($this->never())->method('listActiveMaxUserIdsByRole');

        $resolver = new OrderCustomerNotifyRecipientResolver($adminRepository);
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

        $resolver = new OrderCustomerNotifyRecipientResolver($adminRepository);
        $order = $this->makeOrder(id: 11, maxUserId: 1002, isManual: true);

        $this->assertSame([9001, 9002], $resolver->resolveMaxUserIds($order));
    }

    /** Ручной заказ без менеджеров — пустой список и warning в лог. */
    public function test_resolve_manual_order_without_managers_logs_warning(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveMaxUserIdsByRole')
            ->with(FoodOrderAdminRole::MaxManager)
            ->willReturn([]);

        $resolver = new OrderCustomerNotifyRecipientResolver($adminRepository);
        $order = $this->makeOrder(id: 12, maxUserId: 1002, isManual: true);

        $this->assertSame([], $resolver->resolveMaxUserIds($order));

        $log = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX manual order customer notification: no active max_manager recipients',
        );
        $this->assertSame('warning', $log->level);
        $this->assertSame(12, $log->context['order_id']);
        $this->assertSame(1002, $log->context['max_user_id']);
    }

    /** Создаёт тестовый заказ. */
    private function makeOrder(int $id, int $maxUserId, bool $isManual): FoodOrder
    {
        $order = new FoodOrder([
            'max_user_id' => $maxUserId,
            'is_manual' => $isManual,
        ]);
        $order->id = $id;

        return $order;
    }
}
