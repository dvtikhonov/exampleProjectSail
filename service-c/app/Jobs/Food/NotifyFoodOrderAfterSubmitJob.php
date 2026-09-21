<?php

declare(strict_types=1);

namespace App\Jobs\Food;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Mappers\Max\MaxUserDisplayMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Log\LoggerInterface;

/**
 * Асинхронная отправка MAX-уведомлений после commit оформления заказа.
 */
class NotifyFoodOrderAfterSubmitJob implements ShouldQueue
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
     * @param  OrderDto  $orderDto  DTO заказа для UI Stand
     * @param  int  $orderId  ID заказа в max_food_orders
     * @param  int  $maxUserId  max_user_id заказчика
     * @param  FoodOrderAfterSubmitNotifyKind  $kind  Тип клиентского уведомления
     */
    public function __construct(
        public readonly OrderDto $orderDto,
        public readonly int $orderId,
        public readonly int $maxUserId,
        public readonly FoodOrderAfterSubmitNotifyKind $kind,
    ) {
        $this->afterCommit();
    }

    /**
     * Отправляет уведомление в UI Stand и клиенту/менеджерам.
     */
    public function handle(
        FoodOrderMaxNotifierInterface $maxNotifier,
        FoodOrderStatusNotifierInterface $customerNotifier,
        FoodOrderCustomerReadRepositoryInterface $foodOrderCustomerReadRepository,
        MaxUserIdentityRepositoryInterface $maxUserRepository,
        MaxUserDisplayMapper $maxUserDisplayMapper,
        CacheStoreInterface $cache,
        LoggerInterface $logger,
    ): void {
        $order = $foodOrderCustomerReadRepository->findById($this->orderId);
        $maxUser = $maxUserRepository->findByMaxUserId($this->maxUserId);

        if ($order === null || $maxUser === null) {
            $logger->warning('NotifyFoodOrderAfterSubmitJob skipped: order or user missing', [
                'order_id' => $this->orderId,
                'max_user_id' => $this->maxUserId,
                'kind' => $this->kind->value,
            ]);

            return;
        }

        $uiStandLeg = self::uiStandLegKey();
        if (! $this->isLegCompleted($cache, $uiStandLeg)) {
            $maxNotifier->notify($this->orderDto, $maxUserDisplayMapper->fromRecord($maxUser));
            $this->markLegCompleted($cache, $uiStandLeg);
        }

        $customerLeg = self::customerLegKey($this->kind);
        if (! $this->isLegCompleted($cache, $customerLeg)) {
            match ($this->kind) {
                FoodOrderAfterSubmitNotifyKind::Submitted => $customerNotifier->notifySubmitted($order),
                FoodOrderAfterSubmitNotifyKind::Confirmed => $customerNotifier->notifyConfirmed($order),
            };
            $this->markLegCompleted($cache, $customerLeg);
        }
    }

    private static function uiStandLegKey(): string
    {
        return 'ui_stand';
    }

    private static function customerLegKey(FoodOrderAfterSubmitNotifyKind $kind): string
    {
        return 'customer:'.$kind->value;
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
