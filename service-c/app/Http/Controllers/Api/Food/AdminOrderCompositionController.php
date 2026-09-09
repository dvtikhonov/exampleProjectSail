<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Composition\OrderCompositionUpdateServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\UpdateOrderCompositionRequest;
use App\Http\Responses\AdminOrderDetailResponse;
use Illuminate\Http\JsonResponse;

/**
 * Редактирование состава заказа в очереди проверки.
 */
class AdminOrderCompositionController extends Controller
{
    public function __construct(
        private readonly OrderCompositionUpdateServiceInterface $orderCompositionUpdateService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
        private readonly AdminOrderDetailResponse $adminOrderDetailResponse,
    ) {}

    /**
     * Обновляет состав заказа в очереди проверки.
     */
    public function updateComposition(UpdateOrderCompositionRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderCompositionUpdateService->update(
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->items(),
            ),
        );
    }
}
