<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Enums\Food\Review\OrderReviewStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ApproveOrderReviewRequest;
use App\Http\Requests\Food\RejectOrderReviewRequest;
use App\Http\Responses\AdminOrderDetailResponse;
use Illuminate\Http\JsonResponse;

/**
 * Approve/reject этапов проверки заказа (адрес, оплата, состав).
 */
class AdminOrderReviewStepController extends Controller
{
    public function __construct(
        private readonly OrderReviewStepHandlerInterface $orderReviewStepHandler,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
        private readonly AdminOrderDetailResponse $adminOrderDetailResponse,
    ) {}

    /**
     * Подтверждает адрес доставки.
     */
    public function approveAddress(ApproveOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->approve(
                OrderReviewStep::Address,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            ),
        );
    }

    /**
     * Отклоняет адрес доставки.
     */
    public function rejectAddress(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->reject(
                OrderReviewStep::Address,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->comment(),
            ),
        );
    }

    /**
     * Подтверждает состав заказа.
     */
    public function approveComposition(ApproveOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->approve(
                OrderReviewStep::Composition,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            ),
        );
    }

    /**
     * Отклоняет состав заказа.
     */
    public function rejectComposition(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->reject(
                OrderReviewStep::Composition,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->comment(),
            ),
        );
    }

    /**
     * Подтверждает получение оплаты (проверяющий адреса).
     */
    public function approvePayment(ApproveOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->approve(
                OrderReviewStep::Payment,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
            ),
        );
    }

    /**
     * Отклоняет оплату (проверяющий адреса).
     */
    public function rejectPayment(RejectOrderReviewRequest $request, int $order): JsonResponse
    {
        return $this->adminOrderDetailResponse->fromRecord(
            $this->orderReviewStepHandler->reject(
                OrderReviewStep::Payment,
                $order,
                $this->authenticatedMaxUserResolver->identity(),
                $request->comment(),
            ),
        );
    }
}
