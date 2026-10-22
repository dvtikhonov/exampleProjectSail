<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\Enums\MessengerChannel;

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
     * Внешние id пользователей канала с непустым адресом доставки.
     *
     * Для MAX — `user_id` Bot API; для Telegram — `chat_id` (DM = telegram user id).
     *
     * @return list<int>
     */
    public function listExternalUserIdsWithDeliveryAddress(MessengerChannel $messenger): array;

    /**
     * Обновляет адрес доставки пользователя.
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void;
}
