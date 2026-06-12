<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\AddCartItemRequest;
use App\Http\Requests\Food\UpdateCartItemRequest;
use App\Models\MaxUser;
use App\Services\Food\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getDraftCart($this->maxUser($request));

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $this->maxUser($request),
                $request->dishId(),
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

    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}
