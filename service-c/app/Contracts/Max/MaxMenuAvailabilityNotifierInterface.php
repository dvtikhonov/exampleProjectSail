<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use Carbon\CarbonImmutable;

/**
 * Уведомление в MAX о доступности меню на дату «Блюда на» после cron-синхронизации.
 */
interface MaxMenuAvailabilityNotifierInterface
{
    /**
     * Отправляет текст «Доступно для заказов меню на …» (дата = «Блюда на»)
     * в MAX_REPORT_* и пользователям max_users с сохранённым адресом доставки.
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(CarbonImmutable $menuDate): int;
}
