<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\AiAccessStatusDto;
use App\DTO\Max\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use DateTimeInterface;

/**
 * Включение/выключение доступа AI к базе для пользователей MAX.
 */
interface MaxAiAccessServiceInterface
{
    /**
     * Возвращает текущий статус (какой max_user_id сейчас имеет доступ AI).
     *
     * Сервис делает cleanup просрочки.
     */
    public function getStatus(DateTimeInterface $now): AiAccessStatusDto;

    /**
     * Переключает доступ AI:
     * - если текущий пользователь активен → выключает доступ;
     * - если не активен → включает доступ, только если нет активного доступа у кого-либо.
     *
     * @throws FoodDomainException если активный доступ AI уже включен у другого пользователя (HTTP 409).
     */
    public function toggle(MaxUserIdentity $currentUser, DateTimeInterface $now): AiAccessStatusDto;
}
