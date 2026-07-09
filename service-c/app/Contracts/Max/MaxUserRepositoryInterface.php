<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Репозиторий пользователей MAX mini-app.
 */
interface MaxUserRepositoryInterface
{
    /**
     * Уникальные max_user_id пользователей с сохранённым адресом доставки.
     *
     * @return list<int>
     */
    public function listMaxUserIdsWithDeliveryAddress(): array;
}
