<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Форматирование дат для MAX-уведомлений о заказе.
 */
final class FoodOrderMaxDateFormatter
{
    private const BUSINESS_TIMEZONE = 'Europe/Moscow';

    /**
     * Дата заказа в формате дд.мм для уведомления менеджеру.
     */
    public function formatOrderDate(mixed $createdAt): string
    {
        if ($createdAt instanceof CarbonInterface) {
            return $createdAt->timezone(self::BUSINESS_TIMEZONE)->format('d.m');
        }

        if (is_string($createdAt) && trim($createdAt) !== '') {
            return Carbon::parse($createdAt)
                ->timezone(self::BUSINESS_TIMEZONE)
                ->format('d.m');
        }

        return Carbon::now(self::BUSINESS_TIMEZONE)->format('d.m');
    }

    /**
     * Дата доставки (Y-m-d) в формате дд.мм.гггг для текста уведомления.
     */
    public function formatDeliveryDateLabel(?string $deliveryDate): ?string
    {
        if ($deliveryDate === null || trim($deliveryDate) === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($deliveryDate), $matches) !== 1) {
            return null;
        }

        return sprintf('%s.%s.%s', $matches[3], $matches[2], $matches[1]);
    }
}
