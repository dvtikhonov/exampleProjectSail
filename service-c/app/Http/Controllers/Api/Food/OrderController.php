<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\CustomerOrderSubmissionServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Shared\RequestTimingRecorderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\ListCustomerOrdersRequest;
use App\Http\Requests\Food\ShowCustomerOrderRequest;
use App\Http\Requests\Food\SubmitCustomerOrderRequest;
use App\Support\Profiling\OrderSubmitTiming;
use Illuminate\Http\JsonResponse;

/**
 * API заказов еды для MAX mini-app.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly CustomerOrderQueryServiceInterface $customerOrderQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
        private readonly RequestTimingRecorderInterface $requestTimingRecorder,
    ) {}

    /**
     * Список заказов текущего клиента.
     */
    public function index(ListCustomerOrdersRequest $request): JsonResponse
    {
        $result = $this->customerOrderQueryService->list(
            $this->authenticatedMaxUserResolver->identity(),
            $request->perPage(),
            $request->page(),
        );

        return response()->json([
            'orders' => array_map(
                static fn ($order): array => $order->toArray(),
                $result['orders'],
            ),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Детали заказа клиента.
     */
    public function show(ShowCustomerOrderRequest $request, int $order): JsonResponse
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
     *
     * Server-Timing читается через {@see RequestTimingRecorderInterface}:
     * FormRequest — отдельный экземпляр после createFrom(), метрики пишутся в bound request.
     */
    public function submit(SubmitCustomerOrderRequest $request): JsonResponse
    {
        $order = $this->orderSubmissionService->submit(
            $this->authenticatedMaxUserResolver->identity(),
        );

        $response = response()->json([
            'order' => $order->toArray(),
        ], JsonResponse::HTTP_CREATED);

        $timing = $this->requestTimingRecorder->get(OrderSubmitTiming::REQUEST_ATTRIBUTE);
        if ($timing !== null) {
            $response->headers->set('Server-Timing', OrderSubmitTiming::toServerTimingHeader($timing));
        }

        return $response;
    }
}
