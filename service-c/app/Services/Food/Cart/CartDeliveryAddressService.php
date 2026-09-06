<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Cart\CartDeliveryAddressServiceInterface;
use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Shared\MaxUserIdentity;

/**
 * Обновление адреса доставки в черновике корзины.
 */
class CartDeliveryAddressService implements CartDeliveryAddressServiceInterface
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly CartDraftRepositoryInterface $cartDraftRepository,
        private readonly CartLifecycleRepositoryInterface $cartLifecycleRepository,
    ) {}

    /**
     * Сохраняет адрес доставки в профиле пользователя и в черновике корзины (если есть).
     *
     * Без корзины адрес всё равно сохраняется в профиле MAX — чтобы показывать его в меню.
     */
    public function update(MaxUserIdentity $maxUser, string $deliveryAddress): ?CartDto
    {
        $this->maxUserDeliveryAddressService->persistForMaxUserId($maxUser->maxUserId, $deliveryAddress);

        $cart = $this->cartDraftRepository->findDraftByMaxUserId($maxUser->maxUserId);

        if ($cart === null) {
            return null;
        }

        $this->cartLifecycleRepository->updateDeliveryAddress($cart->id, $deliveryAddress);

        return $this->cartDtoFactory->fromRecord(
            $this->cartLifecycleRepository->refreshForDto($cart->id),
            $maxUser->maxUserId,
        );
    }
}
