<?php

declare(strict_types=1);

namespace App\Services\Food\Chat;

use App\Contracts\Food\Chat\OrderChatAuthorizationServiceInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Chat\OrderMessageAuthorType;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;

/**
 * Проверка прав доступа к чату заказа для клиента и администратора.
 */
class OrderChatAuthorizationService implements OrderChatAuthorizationServiceInterface
{
    /**
     * Запрещает доступ к чату, если пользователь не владелец и не активный админ.
     *
     * Для клиента отказ выглядит как «заказ не найден» (без enumeration).
     *
     * @throws FoodDomainException
     */
    public function assertCanAccessChat(MaxUserIdentity $user, FoodOrderRecord $order): void
    {
        if ($this->canAccessChat($user, $order)) {
            return;
        }

        throw new FoodDomainException('Заказ не найден.', 404);
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

        throw new FoodDomainException('Заказ не найден.', 404);
    }

    /**
     * Является ли пользователь владельцем заказа.
     */
    private function isOrderOwner(MaxUserIdentity $user, FoodOrderRecord $order): bool
    {
        return $order->maxUserId === $user->maxUserId;
    }

    /**
     * Есть ли у пользователя роль из allow-list доступа к чату заказа.
     *
     * Допускаются только AddressReviewer, CompositionReviewer и MaxManager
     * (MenuManager и прочие роли — нет).
     */
    private function isActiveAdmin(MaxUserIdentity $user): bool
    {
        return $user->hasAdminRole(FoodOrderAdminRole::AddressReviewer)
            || $user->hasAdminRole(FoodOrderAdminRole::CompositionReviewer)
            || $user->hasAdminRole(FoodOrderAdminRole::MaxManager);
    }
}
