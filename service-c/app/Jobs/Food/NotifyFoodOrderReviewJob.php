<?php

declare(strict_types=1);

namespace App\Jobs\Food;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Enums\Food\Review\FoodOrderReviewNotifyKind;
use App\Enums\Food\Review\OrderRejectionScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Асинхронная отправка MAX-уведомлений после commit review / правки состава.
 */
class NotifyFoodOrderReviewJob implements ShouldQueue
{
    use Queueable;

    private const MARKER_VALUE = 1;

    /** Максимум попыток при сбое отправки уведомления. */
    public int $tries = 3;

    /** Таймаут одной попытки (секунды). */
    public int $timeout = 30;

    /**
     * Задержки между повторными попытками (секунды).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @param  int  $orderId  ID заказа в max_food_orders
     * @param  FoodOrderReviewNotifyKind  $kind  Тип уведомления
     * @param  OrderRejectionScope|null  $rejectionScope  Scope при Rejected
     * @param  string|null  $idempotencySuffix  Суффикс маркера для CompositionChanged
     */
    public function __construct(
        public readonly int $orderId,
        public readonly FoodOrderReviewNotifyKind $kind,
        public readonly ?OrderRejectionScope $rejectionScope = null,
        public readonly ?string $idempotencySuffix = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Отправляет клиентское уведомление с идемпотентностью по cache-маркеру.
     */
    public function handle(
        FoodOrderStatusNotifierInterface $statusNotifier,
        FoodOrderCompositionNotifierInterface $compositionNotifier,
        FoodOrderCustomerReadRepositoryInterface $foodOrderCustomerReadRepository,
        CacheStoreInterface $cache,
        LoggerInterface $logger,
    ): void {
        $order = $foodOrderCustomerReadRepository->findById($this->orderId);

        if ($order === null) {
            $logger->warning('NotifyFoodOrderReviewJob skipped: order missing', [
                'order_id' => $this->orderId,
                'kind' => $this->kind->value,
            ]);

            return;
        }

        $leg = $this->legKey();
        if ($this->isLegCompleted($cache, $leg)) {
            return;
        }

        match ($this->kind) {
            FoodOrderReviewNotifyKind::Approved => $statusNotifier->notifyConfirmed($order),
            FoodOrderReviewNotifyKind::Rejected => $statusNotifier->notifyRejected(
                $order,
                $this->requireRejectionScope(),
            ),
            FoodOrderReviewNotifyKind::CompositionChanged => $compositionNotifier->notifyCompositionChanged($order),
        };

        $this->markLegCompleted($cache, $leg);
    }

    private function legKey(): string
    {
        return match ($this->kind) {
            FoodOrderReviewNotifyKind::Approved => 'review:approved',
            FoodOrderReviewNotifyKind::Rejected => 'review:rejected:'.$this->requireRejectionScope()->value,
            FoodOrderReviewNotifyKind::CompositionChanged => 'review:composition_changed:'.($this->idempotencySuffix ?? 'default'),
        };
    }

    private function requireRejectionScope(): OrderRejectionScope
    {
        if ($this->rejectionScope === null) {
            throw new InvalidArgumentException('NotifyFoodOrderReviewJob Rejected requires rejectionScope.');
        }

        return $this->rejectionScope;
    }

    private function markerKey(string $leg): string
    {
        return 'food.notify.'.$this->orderId.'.'.$leg;
    }

    private function isLegCompleted(CacheStoreInterface $cache, string $leg): bool
    {
        return $cache->get($this->markerKey($leg)) !== null;
    }

    private function markLegCompleted(CacheStoreInterface $cache, string $leg): void
    {
        $cache->forever($this->markerKey($leg), self::MARKER_VALUE);
    }
}
