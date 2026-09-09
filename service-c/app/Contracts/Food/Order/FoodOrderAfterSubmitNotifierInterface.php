<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;

/**
 * Порт постановки MAX-уведомлений после commit оформления заказа.
 *
 * Изолирует Food-сервисы от Laravel Job / Bus.
 */
interface FoodOrderAfterSubmitNotifierInterface
{
    /**
     * Ставит в очередь (или выполняет синхронно) уведомления после успешного submit.
     *
     * @param  FoodOrderRecord  $order  Запись заказа после commit
     * @param  OrderDto  $dto  DTO заказа для UI Stand
     * @param  int  $maxUserId  max_user_id заказчика
     * @param  FoodOrderAfterSubmitNotifyKind  $kind  Тип клиентского уведомления
     */
    public function notify(
        FoodOrderRecord $order,
        OrderDto $dto,
        int $maxUserId,
        FoodOrderAfterSubmitNotifyKind $kind,
    ): void;
}
