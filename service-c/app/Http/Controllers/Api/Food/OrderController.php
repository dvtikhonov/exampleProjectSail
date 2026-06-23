<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Models\MaxUser;
use App\Services\Food\OrderSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API оформления заказа еды для MAX mini-app.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSubmissionService $orderSubmissionService,
    ) {}

    /**
     * Оформляет заказ из черновой корзины пользователя.
     */
    public function submit(Request $request): JsonResponse
    {
        try {
            $order = $this->orderSubmissionService->submit($this->maxUser($request));
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'order' => $order->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}
