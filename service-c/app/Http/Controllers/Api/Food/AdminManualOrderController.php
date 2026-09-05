<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrder\DraftAfterScanningOrderServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\Order\ManualOrderSubmissionServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\DraftAfterScanningOrderActionRequest;
use App\Http\Requests\Food\Admin\ListManualOrdersRequest;
use App\Http\Requests\Food\Admin\ListManualOrderUsersRequest;
use App\Http\Requests\Food\Admin\ManualAddCartItemRequest;
use App\Http\Requests\Food\Admin\ManualOrderCustomerFormRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartDeliveryAddressRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartItemRequest;
use App\Http\Requests\Food\Admin\ShowManualOrderCartRequest;
use App\Http\Requests\Food\Admin\SubmitManualOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * API ручных заказов для роли max_manager.
 */
class AdminManualOrderController extends Controller
{
    public function __construct(
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly ManualOrderQueryServiceInterface $manualOrderQueryService,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly ManualOrderSubmissionServiceInterface $orderSubmissionService,
        private readonly DraftAfterScanningOrderServiceInterface $draftAfterScanningOrderService,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
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
    public function show(int $order): JsonResponse
    {
        $detail = $this->manualOrderQueryService->show($order);

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
