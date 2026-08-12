<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\OrderSubmissionServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Models\Max\MaxUser;
use App\Support\Profiling\OrderSubmitTiming;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API заказов еды для MAX mini-app.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderSubmissionServiceInterface $orderSubmissionService,
        private readonly CustomerOrderQueryServiceInterface $customerOrderQueryService,
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

        $response = response()->json([
            'order' => $order->toArray(),
        ], JsonResponse::HTTP_CREATED);

        /** @var array{t_tx_ms?: float|int, t_notify_ms?: float|int, t_submit_ms?: float|int}|null $timing */
        $timing = $request->attributes->get(OrderSubmitTiming::REQUEST_ATTRIBUTE);
        if (is_array($timing)) {
            $response->headers->set('Server-Timing', OrderSubmitTiming::toServerTimingHeader($timing));
        }

        return $response;
    }

    /**
     * Текущий аутентифицированный пользователь MAX из запроса.
     */
    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}
