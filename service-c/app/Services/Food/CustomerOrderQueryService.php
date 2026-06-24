<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderRepositoryInterface;
use App\Contracts\Food\OrderMessageRepositoryInterface;
use App\DTO\Food\OrderDto;
use App\DTO\Food\OrderListItemDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;

/**
 * Выборка заказов клиента для API MAX mini-app.
 */
class CustomerOrderQueryService
{
    public function __construct(
        private readonly FoodOrderRepositoryInterface $foodOrderRepository,
        private readonly OrderMessageRepositoryInterface $orderMessageRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @return list<OrderListItemDto>
     */
    public function list(MaxUser $customer): array
    {
        $orders = $this->foodOrderRepository->findByMaxUserId($customer->max_user_id);
        $orderIds = array_map(
            static fn (FoodOrder $order): int => $order->id,
            $orders,
        );
        $chatStats = $this->orderMessageRepository->getChatStatsForOrders(
            $orderIds,
            $customer->max_user_id,
        );

        return array_map(
            function (FoodOrder $order) use ($chatStats): OrderListItemDto {
                $stats = $chatStats[$order->id] ?? [
                    'last_message_at' => null,
                    'unread_count' => 0,
                ];

                return new OrderListItemDto(
                    id: $order->id,
                    status: $order->status->value,
                    restaurantId: $order->restaurant_id,
                    restaurantName: (string) $order->restaurant?->name,
                    total: $this->formatMoney($order->total),
                    lastMessageAt: $stats['last_message_at'],
                    unreadCount: $stats['unread_count'],
                    createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                );
            },
            $orders,
        );
    }

    /**
     * @throws FoodDomainException
     */
    public function show(MaxUser $customer, int $orderId): OrderDto
    {
        $order = $this->foodOrderRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        if ($order->max_user_id !== $customer->max_user_id) {
            throw new FoodDomainException('Forbidden.', 403);
        }

        return $this->mapOrder($order);
    }

    private function mapOrder(FoodOrder $order): OrderDto
    {
        return new OrderDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurant_id,
            restaurantName: (string) $order->restaurant?->name,
            itemsTotal: $this->formatMoney($order->items_total),
            deliveryApplicable: $order->delivery_cost !== null,
            deliveryCost: $order->delivery_cost !== null
                ? $this->formatMoney($order->delivery_cost)
                : null,
            total: $this->formatMoney($order->total),
            deliveryAddress: $order->delivery_address,
            itemsSnapshot: $order->items_snapshot ?? [],
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }

    private function formatMoney(mixed $value): string
    {
        return $this->moneyFormatter->format((float) $value);
    }
}
