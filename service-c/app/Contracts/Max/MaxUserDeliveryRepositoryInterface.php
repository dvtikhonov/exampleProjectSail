<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Адрес доставки пользователей MAX mini-app.
 */
interface MaxUserDeliveryRepositoryInterface
{
    /**
     * Уникальные max_user_id пользователей с сохранённым адресом доставки.
     *
     * @return list<int>
     */
    public function listMaxUserIdsWithDeliveryAddress(): array;

    /**
     * Обновляет адрес доставки пользователя.
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void;
}
