<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\ManualOrderCartServiceInterface;
use App\Contracts\Food\ManualOrderUserQueryServiceInterface;
use App\Contracts\Food\OrderSubmissionServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ListManualOrderUsersRequest;
use App\Http\Requests\Food\Admin\ManualAddCartItemRequest;
use App\Http\Requests\Food\Admin\ManualOrderCustomerFormRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartDeliveryAddressRequest;
use App\Http\Requests\Food\Admin\ManualUpdateCartItemRequest;
use App\Http\Requests\Food\Admin\ShowManualOrderCartRequest;
use App\Http\Requests\Food\Admin\SubmitManualOrderRequest;
use App\Models\MaxUser;
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API ручных заказов для роли max_manager.
 */
class AdminManualOrderController extends Controller
{
    public function __construct(
        private readonly ManualOrderUserQueryServiceInterface $manualOrderUserQueryService,
        private readonly ManualOrderCartServiceInterface $manualOrderCartService,
        private readonly OrderSubmissionServiceInterface $orderSubmissionService,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
    ) {}

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
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        $cart = $this->manualOrderCartService->getDraftCart($customer, $manager);

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultFor($customer),
        ]);
    }

    /**
     * Обновляет адрес доставки в профиле клиента и ручной корзине.
     */
    public function updateDeliveryAddress(ManualUpdateCartDeliveryAddressRequest $request): JsonResponse
    {
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
            $cart = $this->manualOrderCartService->updateDeliveryAddress(
                $customer,
                $manager,
                $request->deliveryAddress(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'cart' => $cart?->toArray(),
            'delivery_address' => $cart?->deliveryAddress
                ?? $this->maxUserDeliveryAddressService->defaultFor($customer)
                ?? $request->deliveryAddress(),
        ]);
    }

    /**
     * Добавляет блюдо в ручную корзину.
     */
    public function storeItem(ManualAddCartItemRequest $request): JsonResponse
    {
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
            $cart = $this->manualOrderCartService->addItem(
                $customer,
                $manager,
                $request->dishId(),
                $request->quantity(),
                $request->comboRef(),
                $request->comboPartnerDishId(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Обновляет количество позиции ручной корзины.
     */
    public function updateItem(ManualUpdateCartItemRequest $request, int $item): JsonResponse
    {
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
            $cart = $this->manualOrderCartService->updateItemQuantity(
                $customer,
                $manager,
                $item,
                $request->quantity(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'cart' => $cart->toArray(),
        ]);
    }

    /**
     * Удаляет позицию из ручной корзины.
     */
    public function destroyItem(ShowManualOrderCartRequest $request, int $item): JsonResponse
    {
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
            $cart = $this->manualOrderCartService->removeItem($customer, $manager, $item);
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'cart' => $cart?->toArray(),
        ]);
    }

    /**
     * Очищает ручную черновую корзину клиента.
     */
    public function clearCart(ShowManualOrderCartRequest $request): JsonResponse
    {
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

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
        try {
            [$customer, $manager] = $this->resolveCustomerAndManager($request);
            $order = $this->orderSubmissionService->submitManual($customer, $manager);
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json([
            'order' => $order->toArray(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Резолвит клиента и текущего менеджера из запроса.
     *
     * @return array{0: MaxUser, 1: MaxUser}
     *
     * @throws FoodDomainException
     */
    private function resolveCustomerAndManager(ManualOrderCustomerFormRequest $request): array
    {
        $customer = $this->manualOrderUserQueryService->findCustomerOrFail(
            $request->customerMaxUserId(),
        );

        return [$customer, $this->manager($request)];
    }

    /**
     * Текущий аутентифицированный менеджер MAX из запроса.
     */
    private function manager(Request $request): MaxUser
    {
        /** @var MaxUser $manager */
        $manager = $request->user();

        return $manager;
    }

    /**
     * JSON-ответ с сообщением доменного исключения.
     */
    private function domainError(FoodDomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], $exception->statusCode());
    }
}
