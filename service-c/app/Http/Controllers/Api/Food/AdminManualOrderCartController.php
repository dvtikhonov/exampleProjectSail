<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ManualAddCartItemRequest;
use App\Http\Requests\Food\Admin\ManualOrderCustomerFormRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartDeliveryAddressRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartItemRequest;
use App\Http\Requests\Food\Admin\ShowManualOrderCartRequest;
use App\Http\Requests\Food\Admin\SubmitManualOrderRequest;
use Illuminate\Http\JsonResponse;

/**
 * Ручная черновая корзина и оформление заказа для роли max_manager.
 */
class AdminManualOrderCartController extends Controller
{
    public function __construct(
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly ManualOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
    ) {}

    /**
     * Возвращает ручную черновую корзину выбранного клиента.
     */
    public function showCart(ShowManualOrderCartRequest $request): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);

        $cart = $this->manualOrderCartService->getDraftCart($customer, $manager);

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultForMaxUserId($customer->maxUserId),
        ]);
    }

    /**
     * Обновляет адрес доставки в профиле клиента и ручной корзине.
     */
    public function updateDeliveryAddress(ManualUpdateCartDeliveryAddressRequest $request): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);
        $cart = $this->manualOrderCartService->updateDeliveryAddress(
            $customer,
            $manager,
            $request->deliveryAddress(),
        );

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultForMaxUserId($customer->maxUserId)
                ?? $request->deliveryAddress(),
        ]);
    }

    /**
     * Добавляет блюдо в ручную корзину.
     */
    public function storeItem(ManualAddCartItemRequest $request): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);
        $cart = $this->manualOrderCartService->addItem(
            $customer,
            $manager,
            $request->dishId(),
            $request->quantity(),
            $request->comboRef(),
            $request->comboPartnerDishId(),
        );

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Обновляет количество позиции ручной корзины.
     */
    public function updateItem(ManualUpdateCartItemRequest $request, int $item): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);
        $cart = $this->manualOrderCartService->updateItemQuantity(
            $customer,
            $manager,
            $item,
            $request->quantity(),
        );

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Удаляет позицию из ручной корзины.
     */
    public function destroyItem(ShowManualOrderCartRequest $request, int $item): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);
        $cart = $this->manualOrderCartService->removeItem($customer, $manager, $item);

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    /**
     * Очищает ручную черновую корзину клиента.
     */
    public function clearCart(ShowManualOrderCartRequest $request): JsonResponse
    {
        [$customer, $manager] = $this->resolveCustomerAndManager($request);

        $this->manualOrderCartService->clear($customer, $manager);

        return response()->json([
            'cart' => null,
        ]);
    }

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
