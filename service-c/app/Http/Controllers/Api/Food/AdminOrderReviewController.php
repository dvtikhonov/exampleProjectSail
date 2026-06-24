<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\RejectOrderReviewRequest;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use App\Services\Food\AdminOrderQueryService;
use App\Services\Food\OrderAddressReviewService;
use App\Services\Food\OrderCompositionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API проверки заказов еды для администраторов MAX mini-app.
 */
class AdminOrderReviewController extends Controller
{
    public function __construct(
        private readonly AdminOrderQueryService $adminOrderQueryService,
        private readonly OrderAddressReviewService $orderAddressReviewService,
        private readonly OrderCompositionReviewService $orderCompositionReviewService,
    ) {}

    /**
     * Возвращает активные роли текущего администратора.
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $this->maxUser($request);

        return response()->json([
            'admin_roles' => $this->adminOrderQueryService->activeRoleValues($admin),
        ]);
    }

    /**
     * Список заказов в очереди проверки.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $scope = (string) $request->query('scope', '');
            $status = (string) $request->query('status', 'pending');

            $orders = $this->adminOrderQueryService->list($this->maxUser($request), $scope, $status);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'orders' => array_map(
                static fn ($order): array => $order->toArray(),
                $orders,
            ),
        ]);
    }

    /**
     * Детали заказа для проверки.
     */
    public function show(Request $request, int $order): JsonResponse
    {
        try {
            $scope = (string) $request->query('scope', '');

            if ($scope === '') {
                throw new FoodDomainException('Query parameter scope is required.', 422);
            }

            $orderDto = $this->adminOrderQueryService->detail($this->maxUser($request), $order, $scope);
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
     * Подтверждает адрес доставки.
     */
    public function approveAddress(Request $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderAddressReviewService->approve($order, $this->maxUser($request));
        });
    }

    /**
     * Отклоняет адрес доставки.
     */
    public function rejectAddress(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderAddressReviewService->reject(
                $order,
                $this->maxUser($request),
                $request->comment(),
            );
        });
    }

    /**
     * Подтверждает состав заказа.
     */
    public function approveComposition(Request $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderCompositionReviewService->approve($order, $this->maxUser($request));
        });
    }

    /**
     * Отклоняет состав заказа.
     */
    public function rejectComposition(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderCompositionReviewService->reject(
                $order,
                $this->maxUser($request),
                $request->comment(),
            );
        });
    }

    /**
     * @param  callable(): FoodOrder  $action
     */
    private function respondReviewDecision(callable $action): JsonResponse
    {
        try {
            $order = $action();
            $orderDto = $this->adminOrderQueryService->detailFromModel($order);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }

    private function maxUser(Request $request): MaxUser
    {
        /** @var MaxUser $maxUser */
        $maxUser = $request->user();

        return $maxUser;
    }
}
