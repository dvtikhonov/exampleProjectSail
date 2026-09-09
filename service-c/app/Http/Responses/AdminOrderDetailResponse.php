<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\FoodOrderRecord;
use Illuminate\Http\JsonResponse;

/**
 * JSON-ответ с деталями заказа для admin review API.
 */
final class AdminOrderDetailResponse
{
    public function __construct(
        private readonly AdminOrderQueryServiceInterface $adminOrderQueryService,
    ) {}

    /**
     * Собирает детали из FoodOrderRecord и возвращает JSON { order: ... }.
     */
    public function fromRecord(FoodOrderRecord $order): JsonResponse
    {
        return $this->fromDetail(
            $this->adminOrderQueryService->detailFromRecord($order),
        );
    }

    /**
     * Возвращает JSON { order: ... } из готового DTO.
     */
    public function fromDetail(AdminOrderDetailDto $orderDto): JsonResponse
    {
        return response()->json([
            'order' => $orderDto->toArray(),
        ]);
    }
}
