<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderItemSyncServiceInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderReviewNotifierInterface;
use App\Contracts\Food\Review\OrderReviewAuthorizationServiceInterface;
use App\Contracts\Food\Review\OrderReviewCompletionServiceInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\FoodOrderReviewNotifyKind;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Review\OrderReviewAuthorizationService;
use App\Services\Food\Review\OrderReviewCompletionService;
use App\Services\Food\Review\OrderReviewStepHandler;
use App\Services\Food\Review\OrderReviewUpdateFactory;
use App\Services\Food\Review\OrderStatusResolver;
use App\Services\Food\Review\UpdateStep\AddressOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\CompositionOrderReviewUpdateStepHandler;
use App\Services\Food\Review\UpdateStep\PaymentOrderReviewUpdateStepHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Unit-тесты OrderReviewStepHandler с моками репозитория и транзакций (без БД).
 */
class OrderReviewStepHandlerTest extends TestCase
{
    /** @var MockObject&FoodOrderWriteRepositoryInterface */
    private FoodOrderWriteRepositoryInterface $writeRepository;

    /** @var MockObject&FoodOrderReviewNotifierInterface */
    private FoodOrderReviewNotifierInterface $reviewNotifier;

    /** @var MockObject&TransactionManagerInterface */
    private TransactionManagerInterface $transactionManager;

    /** @var MockObject&FoodOrderItemSyncServiceInterface */
    private FoodOrderItemSyncServiceInterface $syncService;

    private OrderReviewStepHandler $handler;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeRepository = $this->createMock(FoodOrderWriteRepositoryInterface::class);
        $this->reviewNotifier = $this->createMock(FoodOrderReviewNotifierInterface::class);
        $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
        $this->transactionManager
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $this->syncService = $this->createMock(FoodOrderItemSyncServiceInterface::class);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-08-31T12:00:00+00:00'));

        /** @var OrderReviewAuthorizationServiceInterface $authorizationService */
        $authorizationService = new OrderReviewAuthorizationService;
        /** @var OrderReviewCompletionServiceInterface $completionService */
        $completionService = new OrderReviewCompletionService($this->reviewNotifier);

        $this->handler = new OrderReviewStepHandler(
            $this->writeRepository,
            $authorizationService,
            $this->makeUpdateFactory($clock),
            $completionService,
            $this->reviewNotifier,
            $this->transactionManager,
            $this->syncService,
        );
    }

    /**
     * Собирает OrderReviewUpdateFactory с реестром step-handlers (как в DI).
     */
    private function makeUpdateFactory(ClockInterface $clock): OrderReviewUpdateFactory
    {
        $resolver = new OrderStatusResolver;

        return new OrderReviewUpdateFactory($clock, [
            OrderReviewStep::Address->value => new AddressOrderReviewUpdateStepHandler($resolver),
            OrderReviewStep::Composition->value => new CompositionOrderReviewUpdateStepHandler($resolver),
            OrderReviewStep::Payment->value => new PaymentOrderReviewUpdateStepHandler($resolver),
        ]);
    }

    /** approve обновляет заказ и ставит notify Approved при полном подтверждении. */
    public function test_approve_updates_order_and_notifies_when_fully_confirmed(): void
    {
        $pending = $this->makeOrder(
            id: 42,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Approved,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Approved,
        );
        $confirmed = $this->makeOrder(
            id: 42,
            status: OrderStatus::Confirmed,
            addressReviewStatus: OrderReviewStatus::Approved,
            compositionReviewStatus: OrderReviewStatus::Approved,
            paymentReviewStatus: OrderReviewStatus::Approved,
            compositionReviewedBy: 10_004,
        );
        $admin = new MaxUserIdentity(10_004, [FoodOrderAdminRole::CompositionReviewer]);

        $this->writeRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with(42)
            ->willReturn($pending);
        $this->writeRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $pending,
                $this->callback(static function (FoodOrderUpdateCommand $command) use ($admin): bool {
                    return $command->compositionReviewStatus === OrderReviewStatus::Approved
                        && $command->compositionReviewedBy === $admin->maxUserId
                        && $command->status === OrderStatus::Confirmed
                        && $command->compositionReviewedAt !== null;
                }),
            )
            ->willReturn($confirmed);

        $callOrder = [];
        $this->syncService
            ->expects($this->once())
            ->method('syncIfConfirmed')
            ->with($confirmed)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'sync';
            });
        $this->reviewNotifier
            ->expects($this->once())
            ->method('notify')
            ->with(42, FoodOrderReviewNotifyKind::Approved, null, null)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'notify';
            });

        $result = $this->handler->approve(OrderReviewStep::Composition, 42, $admin);

        $this->assertSame($confirmed, $result);
        $this->assertSame(['sync', 'notify'], $callOrder);
    }

    /** approve без полного подтверждения не шлёт notify Approved. */
    public function test_approve_does_not_notify_when_order_still_pending_review(): void
    {
        $pending = $this->makeOrder(
            id: 43,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
        );
        $afterAddress = $this->makeOrder(
            id: 43,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Approved,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: 10_003,
        );
        $admin = new MaxUserIdentity(10_003, [FoodOrderAdminRole::AddressReviewer]);

        $this->writeRepository->method('findByIdForUpdate')->willReturn($pending);
        $this->writeRepository->method('update')->willReturn($afterAddress);

        $this->reviewNotifier->expects($this->never())->method('notify');

        $result = $this->handler->approve(OrderReviewStep::Address, 43, $admin);

        $this->assertSame($afterAddress, $result);
    }

    /** reject обновляет заказ и ставит notify Rejected. */
    public function test_reject_updates_order_and_notifies_customer(): void
    {
        $pending = $this->makeOrder(
            id: 44,
            status: OrderStatus::PendingReview,
            addressReviewStatus: OrderReviewStatus::Pending,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
        );
        $rejected = $this->makeOrder(
            id: 44,
            status: OrderStatus::Rejected,
            addressReviewStatus: OrderReviewStatus::Rejected,
            compositionReviewStatus: OrderReviewStatus::Pending,
            paymentReviewStatus: OrderReviewStatus::Pending,
            addressReviewedBy: 10_003,
            addressRejectionComment: 'Неверный адрес',
        );
        $admin = new MaxUserIdentity(10_003, [FoodOrderAdminRole::AddressReviewer]);

        $this->writeRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with(44)
            ->willReturn($pending);
        $this->writeRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $pending,
                $this->callback(static function (FoodOrderUpdateCommand $command): bool {
                    return $command->addressReviewStatus === OrderReviewStatus::Rejected
                        && $command->addressRejectionComment === 'Неверный адрес'
                        && $command->status === OrderStatus::Rejected;
                }),
            )
            ->willReturn($rejected);

        $callOrder = [];
        $this->syncService
            ->expects($this->once())
            ->method('syncIfConfirmed')
            ->with($rejected)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'sync';
            });
        $this->reviewNotifier
            ->expects($this->once())
            ->method('notify')
            ->with(44, FoodOrderReviewNotifyKind::Rejected, OrderRejectionScope::Address, null)
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'notify';
            });

        $result = $this->handler->reject(OrderReviewStep::Address, 44, $admin, 'Неверный адрес');

        $this->assertSame($rejected, $result);
        $this->assertSame(['sync', 'notify'], $callOrder);
    }

    /** Отсутствующий заказ приводит к FoodDomainException 404. */
    public function test_approve_throws_when_order_not_found(): void
    {
        $admin = new MaxUserIdentity(10_003, [FoodOrderAdminRole::AddressReviewer]);

        $this->writeRepository
            ->expects($this->once())
            ->method('findByIdForUpdate')
            ->with(999)
            ->willReturn(null);
        $this->writeRepository->expects($this->never())->method('update');

        try {
            $this->handler->approve(OrderReviewStep::Address, 999, $admin);
            $this->fail('Ожидалось FoodDomainException.');
        } catch (FoodDomainException $exception) {
            $this->assertSame('Заказ не найден.', $exception->getMessage());
            $this->assertSame(404, $exception->statusCode());
        }
    }

    /** Без нужной роли approve отклоняется с 403. */
    public function test_approve_throws_when_admin_lacks_required_role(): void
    {
        $pending = $this->makeOrder(id: 45);
        $admin = new MaxUserIdentity(10_005, [FoodOrderAdminRole::MenuManager]);

        $this->writeRepository->method('findByIdForUpdate')->willReturn($pending);
        $this->writeRepository->expects($this->never())->method('update');

        try {
            $this->handler->approve(OrderReviewStep::Address, 45, $admin);
            $this->fail('Ожидалось FoodDomainException.');
        } catch (FoodDomainException $exception) {
            $this->assertSame('Доступ запрещён.', $exception->getMessage());
            $this->assertSame(403, $exception->statusCode());
        }
    }

    private function makeOrder(
        int $id,
        OrderStatus $status = OrderStatus::PendingReview,
        OrderReviewStatus $addressReviewStatus = OrderReviewStatus::Pending,
        OrderReviewStatus $compositionReviewStatus = OrderReviewStatus::Pending,
        OrderReviewStatus $paymentReviewStatus = OrderReviewStatus::Pending,
        ?int $addressReviewedBy = null,
        ?int $compositionReviewedBy = null,
        ?string $addressRejectionComment = null,
    ): FoodOrderRecord {
        return new FoodOrderRecord(
            id: $id,
            cartId: 1,
            maxUserId: 1_002,
            isManual: false,
            createdByMaxUserId: null,
            restaurantId: 1,
            status: $status,
            addressReviewStatus: $addressReviewStatus,
            compositionReviewStatus: $compositionReviewStatus,
            paymentReviewStatus: $paymentReviewStatus,
            addressReviewedBy: $addressReviewedBy,
            addressReviewedAt: $addressReviewedBy !== null ? '2026-08-31T12:00:00+00:00' : null,
            compositionReviewedBy: $compositionReviewedBy,
            compositionReviewedAt: $compositionReviewedBy !== null ? '2026-08-31T12:00:00+00:00' : null,
            addressRejectionComment: $addressRejectionComment,
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
