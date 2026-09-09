<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ListManualOrdersRequest;
use App\Http\Requests\Food\Admin\ListManualOrderUsersRequest;
use App\Http\Requests\Food\Admin\ShowManualOrderRequest;
use Illuminate\Http\JsonResponse;

/**
 * Чтение ручных заказов и пользователей MAX для роли max_manager.
 */
class AdminManualOrderQueryController extends Controller
{
    public function __construct(
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly ManualOrderQueryServiceInterface $manualOrderQueryService,
    ) {}

    /**
     * Список ручных заказов с фильтром по потребителю, периоду, статусу и/или ФИО.
     */
    public function index(ListManualOrdersRequest $request): JsonResponse
    {
        $result = $this->manualOrderQueryService->list(
            $request->searchQuery(),
            $request->dateFrom(),
            $request->dateTo(),
            $request->perPage(),
            $request->customerMaxUserId(),
            $request->status(),
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
     * Детальный просмотр ручного заказа (состав как в корзине).
     */
    public function show(ShowManualOrderRequest $request): JsonResponse
    {
        $detail = $this->manualOrderQueryService->show($request->orderId());

        return response()->json([
            'order' => $detail->toArray(),
        ]);
    }

    /**
     * Поиск и список пользователей MAX для выбора клиента.
     */
    public function users(ListManualOrderUsersRequest $request): JsonResponse
    {
        $result = $this->manualOrderUserQueryService->list(
            $request->searchQuery(),
            $request->perPage(),
        );

        return response()->json([
            'users' => array_map(
                static fn ($user): array => $user->toArray(),
                $result['users'],
            ),
            'meta' => $result['meta'],
        ]);
    }
}
