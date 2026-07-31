<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\Models\Max\MaxUser;

/**
 * Хранение и чтение адреса доставки пользователя MAX.
 */
interface MaxUserDeliveryAddressInterface
{
    /**
     * Возвращает сохранённый адрес доставки пользователя.
     */
    public function defaultFor(MaxUser $maxUser): ?string;

    /**
     * Сохраняет адрес доставки, если он изменился.
     */
    public function persist(MaxUser $maxUser, string $deliveryAddress): void;
}
