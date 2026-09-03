<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\ManualOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\DTO\Food\ManualOrder\ManualOrderDetailDto;
use App\DTO\Food\ManualOrder\ManualOrderListItemDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
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
                fn (FoodOrderRecord $order): ManualOrderListItemDto => $this->mapListItem($order),
                $paginator->items,
            ),
            'meta' => [
                'current_page' => $paginator->currentPage,
                'per_page' => $paginator->perPage,
                'total' => $paginator->total,
                'last_page' => $paginator->lastPage,
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
    private function mapListItem(FoodOrderRecord $order): ManualOrderListItemDto
    {
        return new ManualOrderListItemDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurantId,
            restaurantName: (string) ($order->restaurantName ?? ''),
            customerMaxUserId: $order->maxUserId,
            customerFirstName: $order->customerFirstName,
            customerLastName: $order->customerLastName,
            customerUsername: $order->customerUsername,
            deliveryAddress: $order->deliveryAddress,
            deliveryDate: $order->deliveryDate,
            itemsTotal: $this->moneyFormatter->format($order->itemsTotal),
            deliveryCost: $order->deliveryCost !== null
                ? $this->moneyFormatter->format($order->deliveryCost)
                : null,
            total: $this->moneyFormatter->format($order->total),
            createdAt: $order->createdAt,
        );
    }

    /**
     * Преобразует заказ в детальный DTO для просмотра.
     */
    private function mapDetail(FoodOrderRecord $order): ManualOrderDetailDto
    {
        return new ManualOrderDetailDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurantId,
            restaurantName: (string) ($order->restaurantName ?? ''),
            customerMaxUserId: $order->maxUserId,
            customerFirstName: $order->customerFirstName,
            customerLastName: $order->customerLastName,
            customerUsername: $order->customerUsername,
            deliveryAddress: $order->deliveryAddress,
            deliveryDate: $order->deliveryDate,
            itemsTotal: $this->moneyFormatter->format($order->itemsTotal),
            deliveryApplicable: $order->deliveryCost !== null,
            deliveryCost: $order->deliveryCost !== null
                ? $this->moneyFormatter->format($order->deliveryCost)
                : null,
            total: $this->moneyFormatter->format($order->total),
            itemsSnapshot: $order->itemsSnapshot,
            createdAt: $order->createdAt,
            hasMessages: (bool) ($order->hasMessages ?? false),
        );
    }
}
