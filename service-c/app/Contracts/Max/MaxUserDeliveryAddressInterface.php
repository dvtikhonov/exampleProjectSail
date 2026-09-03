<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;

/**
 * Хранение и чтение адреса доставки пользователя MAX.
 */
interface MaxUserDeliveryAddressInterface
{
    /**
     * Возвращает сохранённый адрес доставки пользователя.
     */
    public function defaultFor(MaxUserRecord $maxUser): ?string;

    /**
     * Возвращает сохранённый адрес доставки по max_user_id.
     */
    public function defaultForMaxUserId(int $maxUserId): ?string;

    /**
     * Сохраняет адрес доставки, если он изменился.
     */
    public function persist(MaxUserRecord $maxUser, string $deliveryAddress): void;

    /**
     * Сохраняет адрес доставки пользователя по max_user_id, если он изменился.
     */
    public function persistForMaxUserId(int $maxUserId, string $deliveryAddress): void;
}
