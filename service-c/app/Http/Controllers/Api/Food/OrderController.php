<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Models\MaxUser;
use App\Contracts\Food\OrderSubmissionServiceInterface;
use App\Services\Food\CustomerOrderQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API заказов еды для MAX mini-app.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSubmissionServiceInterface $orderSubmissionService,
        private readonly CustomerOrderQueryService $customerOrderQueryService,
    ) {}

    /**
     * Список заказов текущего клиента.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->customerOrderQueryService->list($this->maxUser($request));

        return response()->json([
            'orders' => array_map(
                static fn ($order): array => $order->toArray(),
                $orders,
            ),
        ]);
    }

    /**
     * Детали заказа клиента.
     */
    public function show(Request $request, int $order): JsonResponse
    {
        try {
            $orderDto = $this->customerOrderQueryService->show($this->maxUser($request), $order);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }

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
