<?php

declare(strict_types=1);

namespace App\Jobs\Food;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Асинхронная отправка MAX-уведомлений после commit оформления заказа.
 */
class NotifyFoodOrderAfterSubmitJob implements ShouldQueue
{
    use Queueable;

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
        FoodOrderCustomerNotifierInterface $customerNotifier,
    ): void {
        $order = FoodOrder::query()->find($this->orderId);
        $maxUser = MaxUser::query()->where('max_user_id', $this->maxUserId)->first();

        if ($order === null || $maxUser === null) {
            Log::warning('NotifyFoodOrderAfterSubmitJob skipped: order or user missing', [
                'order_id' => $this->orderId,
                'max_user_id' => $this->maxUserId,
                'kind' => $this->kind->value,
            ]);

            return;
        }

        $maxNotifier->notify($this->orderDto, $maxUser);

        match ($this->kind) {
            FoodOrderAfterSubmitNotifyKind::Submitted => $customerNotifier->notifySubmitted($order),
            FoodOrderAfterSubmitNotifyKind::Confirmed => $customerNotifier->notifyConfirmed($order),
        };
    }
}
