<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ManualOrderCustomerFormRequest;
use App\Http\Requests\Food\Admin\SubmitManualOrderRequest;
use Illuminate\Http\JsonResponse;

/**
 * Оформление ручного заказа из корзины менеджера для роли max_manager.
 */
class AdminManualOrderSubmitController extends Controller
{
    public function __construct(
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly ManualOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Оформляет ручной заказ из корзины менеджера от имени клиента.
     */
    public function submit(SubmitManualOrderRequest $request): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);
        $order = $this->orderSubmissionService->submitManual(
            $customer,
            $manager,
            $request->deliveryDate(),
        );

        return response()->json([
            'order' => $order->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Резолвит клиента и текущего менеджера из запроса.
     *
     * @return array{0: MaxUserIdentity, 1: MaxUserIdentity}
     *
     * @throws FoodDomainException
     */
    private function resolveCustomerAndManager(ManualOrderCustomerFormRequest $request): array
    {
        $customer = $this->manualOrderUserQueryService->findCustomerOrFail(
            $request->customerMaxUserId(),
        );

        return [$customer, $this->authenticatedMaxUserResolver->identity()];
    }
}
