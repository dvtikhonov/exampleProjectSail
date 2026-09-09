<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\AdminReviewMeRequest;
use App\Http\Requests\Food\Admin\ListAdminOrdersRequest;
use App\Http\Requests\Food\Admin\ShowAdminOrderReviewRequest;
use Illuminate\Http\JsonResponse;

/**
 * Чтение очереди проверки заказов для администраторов MAX mini-app.
 */
class AdminOrderReviewQueryController extends Controller
{
    public function __construct(
        private readonly AdminOrderQueryServiceInterface $adminOrderQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Возвращает активные роли текущего администратора.
     */
    public function me(AdminReviewMeRequest $request): JsonResponse
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
    public function show(ShowAdminOrderReviewRequest $request, int $order): JsonResponse
    {
        $orderDto = $this->adminOrderQueryService->detail(
            $this->authenticatedMaxUserResolver->identity(),
            $order,
            $request->scope(),
        );

        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }
}
