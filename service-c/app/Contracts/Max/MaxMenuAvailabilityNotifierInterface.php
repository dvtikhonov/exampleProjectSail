<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Уведомление в MAX о доступности меню на завтра (дата заказа) после cron-синхронизации.
 */
interface MaxMenuAvailabilityNotifierInterface
{
    /**
     * Отправляет текст «Доступно для заказов меню на …» (дата = завтра MSK)
     * в MAX_REPORT_* и пользователям max_users с сохранённым адресом доставки.
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(): int;
}
