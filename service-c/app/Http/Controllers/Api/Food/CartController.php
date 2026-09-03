<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Cart\CartDeliveryAddressServiceInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\AddCartItemRequest;
use App\Http\Requests\Food\UpdateCartDeliveryAddressRequest;
use App\Http\Requests\Food\UpdateCartItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API корзины заказа еды для MAX mini-app.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
        private readonly CartDeliveryAddressServiceInterface $cartDeliveryAddressService,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly AuthenticatedMaxUserResolverInterface $maxUserResolver,
    ) {}

    /**
     * Возвращает текущую черновую корзину пользователя.
     *
     * `delivery_address` — адрес из корзины или сохранённый в профиле (для шапки меню без корзины).
     */
    public function show(Request $request): JsonResponse
    {
        $identity = $this->maxUserResolver->identity();
        $cart = $this->cartService->getDraftCart($identity);

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultForMaxUserId($identity->maxUserId),
        ]);
    }

    /**
     * Обновляет адрес доставки в корзине и/или профиле пользователя.
     */
    public function updateDeliveryAddress(UpdateCartDeliveryAddressRequest $request): JsonResponse
    {
        $identity = $this->maxUserResolver->identity();
        $deliveryAddress = $request->deliveryAddress();

        $cart = $this->cartDeliveryAddressService->update(
            $identity,
            $deliveryAddress,
        );

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultForMaxUserId($identity->maxUserId)
                ?? $deliveryAddress,
        ]);
    }

    /**
     * Добавляет блюдо в корзину.
     */
    public function store(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            $this->maxUserResolver->identity(),
            $request->dishId(),
            $request->quantity(),
            $request->comboRef(),
            $request->comboPartnerDishId(),
        );

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Обновляет количество позиции корзины.
     */
    public function update(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        $cart = $this->cartService->updateItemQuantity(
            $this->maxUserResolver->identity(),
            $item,
            $request->quantity(),
        );

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Удаляет позицию из корзины.
     */
    public function destroy(Request $request, int $item): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            $this->maxUserResolver->identity(),
            $item,
        );

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    /**
     * Очищает черновую корзину пользователя.
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear(
            $this->maxUserResolver->identity(),
        );

        return response()->json([
            'cart' => null,
        ]);
    }
}
