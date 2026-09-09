<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\DraftAfterScanningOrderActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Действия над заказами «Черновик после сканирования» для роли max_manager.
 */
class AdminDraftAfterScanningOrderController extends Controller
{
    public function __construct(
        private readonly DraftAfterScanningOrderServiceInterface $draftAfterScanningOrderService,
        private readonly ManualOrderQueryServiceInterface $manualOrderQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Переводит заказ «Черновик после сканирования» в статус «Выполнен».
     */
    public function complete(DraftAfterScanningOrderActionRequest $request): JsonResponse
    {
        $this->draftAfterScanningOrderService->complete(
            $request->orderId(),
            $this->authenticatedMaxUserResolver->identity(),
        );
        $detail = $this->manualOrderQueryService->show($request->orderId());

        return response()->json([
            'order' => $detail->toArray(),
        ]);
    }

    /**
     * Переносит позиции заказа «Черновик после сканирования» в ручную корзину клиента.
     */
    public function moveToCart(DraftAfterScanningOrderActionRequest $request): JsonResponse
    {
        $result = $this->draftAfterScanningOrderService->moveToCart(
            $request->orderId(),
            $this->authenticatedMaxUserResolver->identity(),
        );

        return response()->json($result->toArray());
    }

    /**
     * Удаляет ручной заказ в статусе «Черновик после сканирования».
     */
    public function destroy(DraftAfterScanningOrderActionRequest $request): Response
    {
        $this->draftAfterScanningOrderService->delete(
            $request->orderId(),
            $this->authenticatedMaxUserResolver->identity(),
        );

        return response()->noContent();
    }
}
