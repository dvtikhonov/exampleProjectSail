<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Уведомление в MAX о доступности меню на сегодня после cron-синхронизации.
 */
interface MaxMenuAvailabilityNotifierInterface
{
    /**
     * Отправляет текст «Доступно для заказов меню на …» в MAX_REPORT_* и пользователям
     * max_users с сохранённым адресом доставки.
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(): int;
}
