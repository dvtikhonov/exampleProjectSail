<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderSubmissionServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Support\Profiling\OrderSubmitTiming;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API заказов еды для MAX mini-app.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly CustomerOrderQueryServiceInterface $customerOrderQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Список заказов текущего клиента.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->customerOrderQueryService->list(
            $this->authenticatedMaxUserResolver->identity(),
        );

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
        $orderDto = $this->customerOrderQueryService->show(
            $this->authenticatedMaxUserResolver->identity(),
            $order,
        );

        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }

    /**
     * Оформляет заказ из черновой корзины пользователя.
     */
    public function submit(Request $request): JsonResponse
    {
        $order = $this->orderSubmissionService->submit(
            $this->authenticatedMaxUserResolver->identity(),
        );

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
}
