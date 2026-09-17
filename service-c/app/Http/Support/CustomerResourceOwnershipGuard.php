<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Models\Food\CartItem;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;

/**
 * HTTP-адаптер проверки ownership клиентских resource-ID (Eloquent на границе HTTP).
 *
 * Сервисный слой остаётся defense-in-depth; здесь — ранний отказ в FormRequest::authorize().
 */
final class CustomerResourceOwnershipGuard
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Владеет ли пользователь заказом.
     *
     * @return bool|null true — владелец; false — чужой заказ; null — заказ не найден
     */
    public function ownsOrder(MaxUser $user, int $orderId): ?bool
    {
        $ownerMaxUserId = FoodOrder::query()
            ->whereKey($orderId)
            ->value('max_user_id');

        if ($ownerMaxUserId === null) {
            return null;
        }

        return (int) $ownerMaxUserId === (int) $user->max_user_id;
    }

    /**
     * Может ли пользователь читать/писать чат заказа (владелец или активный админ).
     *
     * @return bool|null true — доступ есть; false — запрещён; null — заказ не найден
     */
    public function canAccessOrderChat(MaxUser $user, int $orderId): ?bool
    {
        $owns = $this->ownsOrder($user, $orderId);

        if ($owns === null) {
            return null;
        }

        if ($owns) {
            return true;
        }

        return $this->foodOrderAdminRepository->getActiveRoles((int) $user->max_user_id) !== [];
    }

    /**
     * Принадлежит ли позиция корзины текущему пользователю.
     *
     * false — позиция отсутствует или чужая (в API принято отвечать 404).
     */
    public function ownsCartItem(MaxUser $user, int $cartItemId): bool
    {
        $item = CartItem::query()
            ->with('cart:id,max_user_id')
            ->find($cartItemId);

        if ($item === null || $item->cart === null) {
            return false;
        }

        return (int) $item->cart->max_user_id === (int) $user->max_user_id;
    }
}
