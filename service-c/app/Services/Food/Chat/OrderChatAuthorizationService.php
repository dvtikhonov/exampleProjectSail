<?php

declare(strict_types=1);

namespace App\Services\Food\Chat;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Exceptions\Food\FoodDomainException;

/**
 * Проверка прав доступа к чату заказа для клиента и администратора.
 */
class OrderChatAuthorizationService
{
    /**
     * Запрещает доступ к чату, если пользователь не владелец и не активный админ.
     *
     * @throws FoodDomainException
     */
    public function assertCanAccessChat(MaxUserIdentity $user, FoodOrderRecord $order): void
    {
        if ($this->canAccessChat($user, $order)) {
            return;
        }

        throw new FoodDomainException('Доступ запрещён.', 403);
    }

    /**
     * Проверяет, может ли пользователь читать и писать в чат заказа.
     */
    public function canAccessChat(MaxUserIdentity $user, FoodOrderRecord $order): bool
    {
        return $this->isOrderOwner($user, $order) || $this->isActiveAdmin($user);
    }

    /**
     * Определяет тип автора сообщения (клиент или админ).
     *
     * @throws FoodDomainException
     */
    public function resolveAuthorType(MaxUserIdentity $user, FoodOrderRecord $order): OrderMessageAuthorType
    {
        if ($this->isOrderOwner($user, $order)) {
            return OrderMessageAuthorType::Customer;
        }

        if ($this->isActiveAdmin($user)) {
            return OrderMessageAuthorType::Admin;
        }

        throw new FoodDomainException('Доступ запрещён.', 403);
    }

    /**
     * Является ли пользователь владельцем заказа.
     */
    private function isOrderOwner(MaxUserIdentity $user, FoodOrderRecord $order): bool
    {
        return $order->maxUserId === $user->maxUserId;
    }

    /**
     * Есть ли у пользователя хотя бы одна активная роль админа заказов.
     */
    private function isActiveAdmin(MaxUserIdentity $user): bool
    {
        return $user->adminRoles !== [];
    }
}
