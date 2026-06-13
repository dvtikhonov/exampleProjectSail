<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\CartDto;
use App\Enums\Food\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;

class CartDeliveryAddressService
{
    public function __construct(
        private readonly CartDtoFactory $cartDtoFactory,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
    ) {}

    public function update(MaxUser $maxUser, string $deliveryAddress): CartDto
    {
        $cart = Cart::query()
            ->where('max_user_id', $maxUser->max_user_id)
            ->where('status', CartStatus::Draft)
            ->first();

        if ($cart === null) {
            throw new FoodDomainException('Cart is empty.', 404);
        }

        $cart->update(['delivery_address' => $deliveryAddress]);
        $this->maxUserDeliveryAddressService->persist($maxUser, $deliveryAddress);

        return $this->cartDtoFactory->fromModel(
            $cart->fresh(['restaurant', 'items.dish']),
            $maxUser,
        );
    }
}
