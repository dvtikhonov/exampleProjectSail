<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Composition\OrderCompositionUpdateServiceInterface;
use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ListAdminOrdersRequest;
use App\Http\Requests\Food\RejectOrderReviewRequest;
use App\Http\Requests\Food\UpdateOrderCompositionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API проверки заказов еды для администраторов MAX mini-app.
 */
class AdminOrderReviewController extends Controller
{
    public function __construct(
        private readonly AdminOrderQueryServiceInterface $adminOrderQueryService,
        private readonly OrderReviewStepHandlerInterface $orderReviewStepHandler,
        private readonly OrderCompositionUpdateServiceInterface $orderCompositionUpdateService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Возвращает активные роли текущего администратора.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'admin_roles' => $this->adminOrderQueryService->activeRoleValues(
                $this->authenticatedMaxUserResolver->identity(),
            ),
        ]);
    }

    /**
     * Список заказов в очереди проверки.
     */
    public function index(ListAdminOrdersRequest $request): JsonResponse
    {
        $result = $this->adminOrderQueryService->list(
            $this->authenticatedMaxUserResolver->identity(),
            $request->scope(),
            $request->listStatus(),
            $request->perPage(),
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
     * Детали заказа для проверки.
     */
    public function show(Request $request, int $order): JsonResponse
    {
        $scope = (string) $request->query('scope', '');

        if ($scope === '') {
            throw new FoodDomainException('Параметр запроса scope обязателен.', 422);
        }

        $orderDto = $this->adminOrderQueryService->detail(
            $this->authenticatedMaxUserResolver->identity(),
            $order,
            $scope,
        );

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
            return $this->orderReviewStepHandler->approve(
                OrderReviewStep::Address,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            );
        });
    }

    /**
     * Отклоняет адрес доставки.
     */
    public function rejectAddress(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderReviewStepHandler->reject(
                OrderReviewStep::Address,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
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
            return $this->orderReviewStepHandler->approve(
                OrderReviewStep::Composition,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            );
        });
    }

    /**
     * Отклоняет состав заказа.
     */
    public function rejectComposition(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderReviewStepHandler->reject(
                OrderReviewStep::Composition,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->comment(),
            );
        });
    }

    /**
     * Обновляет состав заказа в очереди проверки.
     */
    public function updateComposition(UpdateOrderCompositionRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderCompositionUpdateService->update(
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->items(),
            );
        });
    }

    /**
     * Подтверждает получение оплаты (проверяющий адреса).
     */
    public function approvePayment(Request $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderReviewStepHandler->approve(
                OrderReviewStep::Payment,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            );
        });
    }

    /**
     * Отклоняет оплату (проверяющий адреса).
     */
    public function rejectPayment(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->respondReviewDecision(function () use ($request, $order) {
            return $this->orderReviewStepHandler->reject(
                OrderReviewStep::Payment,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->comment(),
            );
        });
    }

    /**
     * @param  callable(): FoodOrderRecord  $action
     */
    private function respondReviewDecision(callable $action): JsonResponse
    {
        $order = $action();
        $orderDto = $this->adminOrderQueryService->detailFromRecord($order);

        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }
}
