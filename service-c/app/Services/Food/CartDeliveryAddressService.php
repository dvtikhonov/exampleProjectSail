<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\CartRepositoryInterface;
use App\DTO\Food\CartDto;
use App\Exceptions\Food\FoodDomainException;
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
     * Сохраняет адрес доставки в корзине и профиле пользователя.
     *
     * @throws FoodDomainException
     */
    public function update(MaxUser $maxUser, string $deliveryAddress): CartDto
    {
        $cart = $this->cartRepository->findDraftByMaxUserId($maxUser->max_user_id);

        if ($cart === null) {
            throw new FoodDomainException('Cart is empty.', 404);
        }

        $this->cartRepository->updateDeliveryAddress($cart, $deliveryAddress);
        $this->maxUserDeliveryAddressService->persist($maxUser, $deliveryAddress);

        return $this->cartDtoFactory->fromModel(
            $this->cartRepository->refreshForDto($cart),
            $maxUser,
        );
    }
}
