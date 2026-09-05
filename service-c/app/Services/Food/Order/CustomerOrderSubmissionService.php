<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Order\CustomerOrderSubmissionServiceInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\RequestTimingRecorderInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Exceptions\Food\FoodDomainException;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;
use App\Support\Profiling\OrderSubmitTiming;
use Psr\Log\LoggerInterface;

/**
 * Клиентское оформление заказа из черновика корзины и уведомление MAX.
 */
class CustomerOrderSubmissionService implements CustomerOrderSubmissionServiceInterface
{
    public function __construct(
        private readonly OrderFromCartCreator $orderFromCartCreator,
        private readonly CartDraftRepositoryInterface $cartDraftRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly JobDispatcherInterface $jobDispatcher,
        private readonly RequestTimingRecorderInterface $requestTimingRecorder,
    ) {}

    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUserIdentity $user): OrderDto
    {
        $submitStartedAt = hrtime(true);

        /** @var array{order: FoodOrderRecord, dto: OrderDto} $result */
        $txStartedAt = hrtime(true);
        $result = $this->transactionManager->run(function () use ($user): array {
            $cart = $this->cartDraftRepository->findDraftForUpdate($user->maxUserId);

            return $this->orderFromCartCreator->create(
                cart: $cart,
                customerMaxUserId: $user->maxUserId,
                isManual: false,
                createdByMaxUserId: null,
                draftAfterScanning: false,
            );
        });
        // tTxMs — длительность транзакции: снимок корзины, создание заказа, markAsSubmitted.
        $tTxMs = $this->elapsedMs($txStartedAt);

        $notifyStartedAt = hrtime(true);
        $this->dispatchAfterSubmitNotify(
            order: $result['order'],
            dto: $result['dto'],
            maxUserId: $user->maxUserId,
            kind: FoodOrderAfterSubmitNotifyKind::Submitted,
        );
        // tNotifyMs — постановка NotifyFoodOrderAfterSubmitJob в очередь (после commit, без ожидания MAX API).
        $tNotifyMs = $this->elapsedMs($notifyStartedAt);
        // tSubmitMs — полное время submit(): транзакция + dispatch уведомления.
        $tSubmitMs = $this->elapsedMs($submitStartedAt);

        $this->recordSubmitTiming($tTxMs, $tNotifyMs, $tSubmitMs);

        return $result['dto'];
    }

    /**
     * Ставит в очередь MAX-уведомления после commit транзакции оформления.
     */
    private function dispatchAfterSubmitNotify(
        FoodOrderRecord $order,
        OrderDto $dto,
        int $maxUserId,
        FoodOrderAfterSubmitNotifyKind $kind,
    ): void {
        $this->jobDispatcher->dispatch(new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUserId,
            kind: $kind,
        ));
    }

    /**
     * Пишет профилирование submit в лог и (при HTTP) в атрибут запроса для Server-Timing.
     *
     * @param  float  $tTxMs  мс транзакции (корзина → заказ)
     * @param  float  $tNotifyMs  мс dispatch NotifyFoodOrderAfterSubmitJob (не время доставки в MAX)
     * @param  float  $tSubmitMs  мс всего submit() = tx + notify
     */
    private function recordSubmitTiming(float $tTxMs, float $tNotifyMs, float $tSubmitMs): void
    {
        $timing = [
            't_tx_ms' => round($tTxMs, 1),
            't_notify_ms' => round($tNotifyMs, 1),
            't_submit_ms' => round($tSubmitMs, 1),
        ];

        $this->logger->info('order.submit.profile', $timing);

        $this->requestTimingRecorder->record(OrderSubmitTiming::REQUEST_ATTRIBUTE, $timing);
    }

    /**
     * @param  int  $startedAtHrtime  значение hrtime(true)
     */
    private function elapsedMs(int $startedAtHrtime): float
    {
        return (hrtime(true) - $startedAtHrtime) / 1_000_000;
    }
}
