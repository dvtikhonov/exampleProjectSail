<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\DTO\Food\CartDto;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;

/**
 * Обновление адреса доставки в черновике корзины.
 */
class CartDeliveryAddressService
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
        private readonly CartRepositoryInterface $cartRepository,
    ) {}

    /**
     * Сохраняет адрес доставки в профиле пользователя и в черновике корзины (если есть).
     *
     * Без корзины адрес всё равно сохраняется в профиле MAX — чтобы показывать его в меню.
     */
    public function update(MaxUser $maxUser, string $deliveryAddress): ?CartDto
    {
        $this->maxUserDeliveryAddressService->persist($maxUser, $deliveryAddress);

        $cart = $this->cartRepository->findDraftByMaxUserId($maxUser->max_user_id);

        if ($cart === null) {
            return null;
        }

        $this->cartRepository->updateDeliveryAddress($cart, $deliveryAddress);

        return $this->cartDtoFactory->fromModel(
            $this->cartRepository->refreshForDto($cart),
            $maxUser,
        );
    }
}
