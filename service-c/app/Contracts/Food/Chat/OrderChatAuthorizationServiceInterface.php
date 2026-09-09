<?php

declare(strict_types=1);

namespace App\Contracts\Food\Chat;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Exceptions\Food\FoodDomainException;

/**
 * Проверка прав доступа к чату заказа для клиента и администратора.
 */
interface OrderChatAuthorizationServiceInterface
{
    /**
     * Запрещает доступ к чату, если пользователь не владелец и не активный админ.
     *
     * @throws FoodDomainException
     */
    public function assertCanAccessChat(MaxUserIdentity $user, FoodOrderRecord $order): void;

    /**
     * Проверяет, может ли пользователь читать и писать в чат заказа.
     */
    public function canAccessChat(MaxUserIdentity $user, FoodOrderRecord $order): bool;

    /**
     * Определяет тип автора сообщения (клиент или админ).
     *
     * @throws FoodDomainException
     */
    public function resolveAuthorType(MaxUserIdentity $user, FoodOrderRecord $order): OrderMessageAuthorType;
}
