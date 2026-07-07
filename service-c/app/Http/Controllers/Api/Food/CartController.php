<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\CartServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\AddCartItemRequest;
use App\Http\Requests\Food\UpdateCartDeliveryAddressRequest;
use App\Http\Requests\Food\UpdateCartItemRequest;
use App\Models\MaxUser;
use App\Services\Food\CartDeliveryAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API корзины заказа еды для MAX mini-app.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
        private readonly CartDeliveryAddressService $cartDeliveryAddressService,
    ) {}

    /**
     * Возвращает текущую черновую корзину пользователя.
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getDraftCart($this->maxUser($request));

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    /**
     * Обновляет адрес доставки в корзине.
     */
    public function updateDeliveryAddress(UpdateCartDeliveryAddressRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartDeliveryAddressService->update(
                $this->maxUser($request),
                $request->deliveryAddress(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Добавляет блюдо в корзину.
     */
    public function store(AddCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $this->maxUser($request),
                $request->dishId(),
                $request->quantity(),
                $request->comboRef(),
                $request->comboPartnerDishId(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Обновляет количество позиции корзины.
     */
    public function update(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        try {
            $cart = $this->cartService->updateItemQuantity(
                $this->maxUser($request),
                $item,
                $request->quantity(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Удаляет позицию из корзины.
     */
    public function destroy(Request $request, int $item): JsonResponse
    {
        try {
            $cart = $this->cartService->removeItem($this->maxUser($request), $item);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    /**
     * Очищает черновую корзину пользователя.
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear($this->maxUser($request));

        return response()->json([
            'cart' => null,
        ]);
    }

    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}
