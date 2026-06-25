<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Этап проверки заказа, на котором было принято решение об отклонении.
 */
enum OrderRejectionScope: string
{
    case Address = 'address';
    case Composition = 'composition';
    case Payment = 'payment';

    /**
     * Человекочитаемое название этапа для уведомления клиенту.
     */
    public function label(): string
    {
        return match ($this) {
            self::Address => 'адрес доставки',
            self::Composition => 'состав заказа',
            self::Payment => 'оплата',
        };
    }
}
