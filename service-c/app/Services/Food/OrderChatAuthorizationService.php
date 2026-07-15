<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\OrderMessageAuthorType;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;

/**
 * Проверка прав доступа к чату заказа для клиента и администратора.
 */
class OrderChatAuthorizationService
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Запрещает доступ к чату, если пользователь не владелец и не активный админ.
     *
     * @throws FoodDomainException
     */
    public function assertCanAccessChat(MaxUser $user, FoodOrder $order): void
    {
        if ($this->canAccessChat($user, $order)) {
            return;
        }

        throw new FoodDomainException('Forbidden.', 403);
    }

    /**
     * Проверяет, может ли пользователь читать и писать в чат заказа.
     */
    public function canAccessChat(MaxUser $user, FoodOrder $order): bool
    {
        return $this->isOrderOwner($user, $order) || $this->isActiveAdmin($user);
    }

    /**
     * Определяет тип автора сообщения (клиент или админ).
     *
     * @throws FoodDomainException
     */
    public function resolveAuthorType(MaxUser $user, FoodOrder $order): OrderMessageAuthorType
    {
        if ($this->isOrderOwner($user, $order)) {
            return OrderMessageAuthorType::Customer;
        }

        if ($this->isActiveAdmin($user)) {
            return OrderMessageAuthorType::Admin;
        }

        throw new FoodDomainException('Forbidden.', 403);
    }

    /**
     * Является ли пользователь владельцем заказа.
     */
    private function isOrderOwner(MaxUser $user, FoodOrder $order): bool
    {
        return $order->max_user_id === $user->max_user_id;
    }

    /**
     * Есть ли у пользователя хотя бы одна активная роль админа заказов.
     */
    private function isActiveAdmin(MaxUser $user): bool
    {
        return $this->foodOrderAdminRepository->getActiveRoles($user->max_user_id) !== [];
    }
}
