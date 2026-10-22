<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

use DateTimeInterface;

/**
 * Уведомление в Telegram о доступности меню на дату «Блюда на» после cron-синхронизации.
 */
interface TelegramMenuAvailabilityNotifierInterface
{
    /**
     * Отправляет текст «Доступно для заказов меню на …» (дата = «Блюда на»)
     * в TELEGRAM_REPORT_* и пользователям messenger=telegram с сохранённым адресом доставки.
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(DateTimeInterface $menuDate): int;
}
