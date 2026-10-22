<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Текст клиентского уведомления о доступности меню (MAX / Telegram).
 */
final class MenuAvailabilityClientNotificationText
{
    private const string TIMEZONE = 'Europe/Moscow';

    /**
     * Формирует «Доступно для заказов меню на ДД.ММ.ГГГГ» (дата в MSK).
     */
    public static function forDate(DateTimeInterface $date): string
    {
        return sprintf(
            'Доступно для заказов меню на %s',
            DateTimeImmutable::createFromInterface($date)
                ->setTimezone(new DateTimeZone(self::TIMEZONE))
                ->format('d.m.Y'),
        );
    }
}
