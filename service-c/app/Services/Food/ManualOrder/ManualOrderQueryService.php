<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\DTO\Food\ManualOrder\ManualOrderDetailDto;
use App\DTO\Food\ManualOrder\ManualOrderListItemDto;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\FoodOrder;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Выборка ручных заказов для роли max_manager.
 */
class ManualOrderQueryService implements ManualOrderQueryServiceInterface
{
    public function __construct(
        private readonly FoodOrderAdminReadRepositoryInterface $foodOrderReadRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function list(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): array {
        $paginator = $this->foodOrderReadRepository->paginateManualOrders(
            $query,
            $dateFrom,
            $dateTo,
            $perPage,
            $customerMaxUserId,
            $status,
        );

        $totalAmount = $this->foodOrderReadRepository->sumManualOrdersTotal(
            $query,
            $dateFrom,
            $dateTo,
            $customerMaxUserId,
            $status,
        );

        return [
            'orders' => array_map(
                fn (FoodOrder $order): ManualOrderListItemDto => $this->mapListItem($order),
                $paginator->items(),
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'total_amount' => $this->moneyFormatter->format($totalAmount),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function show(int $orderId): ManualOrderDetailDto
    {
        $order = $this->foodOrderReadRepository->findManualOrderById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * Преобразует заказ в DTO элемента списка ручных заказов.
     */
    private function mapListItem(FoodOrder $order): ManualOrderListItemDto
    {
        return new ManualOrderListItemDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurant_id,
            restaurantName: (string) $order->restaurant?->name,
            customerMaxUserId: $order->max_user_id,
            customerFirstName: $order->maxUser?->first_name,
            customerLastName: $order->maxUser?->last_name,
            customerUsername: $order->maxUser?->username,
            deliveryAddress: $order->delivery_address,
            itemsTotal: $this->moneyFormatter->format($order->items_total),
            deliveryCost: $order->delivery_cost !== null
                ? $this->moneyFormatter->format($order->delivery_cost)
                : null,
            total: $this->moneyFormatter->format($order->total),
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }

    /**
     * Преобразует заказ в детальный DTO для просмотра.
     */
    private function mapDetail(FoodOrder $order): ManualOrderDetailDto
    {
        return new ManualOrderDetailDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurant_id,
            restaurantName: (string) $order->restaurant?->name,
            customerMaxUserId: $order->max_user_id,
            customerFirstName: $order->maxUser?->first_name,
            customerLastName: $order->maxUser?->last_name,
            customerUsername: $order->maxUser?->username,
            deliveryAddress: $order->delivery_address,
            itemsTotal: $this->moneyFormatter->format($order->items_total),
            deliveryApplicable: $order->delivery_cost !== null,
            deliveryCost: $order->delivery_cost !== null
                ? $this->moneyFormatter->format($order->delivery_cost)
                : null,
            total: $this->moneyFormatter->format($order->total),
            itemsSnapshot: $order->items_snapshot ?? [],
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
            hasMessages: $order->messages()->exists(),
        );
    }
}
