<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Order\CustomerOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Order\OrderListItemDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Выборка заказов клиента для API MAX mini-app.
 */
class CustomerOrderQueryService implements CustomerOrderQueryServiceInterface
{
    public function __construct(
        private readonly FoodOrderCustomerReadRepositoryInterface $foodOrderReadRepository,
        private readonly OrderMessageRepositoryInterface $orderMessageRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * Возвращает список заказов клиента.
     *
     * @return list<OrderListItemDto>
     */
    public function list(MaxUserIdentity $customer): array
    {
        $orders = $this->foodOrderReadRepository->findByMaxUserId($customer->maxUserId);
        $orderIds = array_map(
            static fn (FoodOrderRecord $order): int => $order->id,
            $orders,
        );
        $chatStats = $this->orderMessageRepository->getChatStatsForOrders(
            $orderIds,
            $customer->maxUserId,
        );

        return array_map(
            function (FoodOrderRecord $order) use ($chatStats): OrderListItemDto {
                $stats = $chatStats[$order->id] ?? [
                    'last_message_at' => null,
                    'unread_count' => 0,
                ];

                return new OrderListItemDto(
                    id: $order->id,
                    status: $order->status->value,
                    restaurantId: $order->restaurantId,
                    restaurantName: (string) ($order->restaurantName ?? ''),
                    total: $this->formatMoney($order->total),
                    lastMessageAt: $stats['last_message_at'],
                    unreadCount: $stats['unread_count'],
                    createdAt: $order->createdAt,
                );
            },
            $orders,
        );
    }

    /**
     * Возвращает заказ клиента по идентификатору.
     *
     * @throws FoodDomainException
     */
    public function show(MaxUserIdentity $customer, int $orderId): OrderDto
    {
        $order = $this->foodOrderReadRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        if ($order->maxUserId !== $customer->maxUserId) {
            throw new FoodDomainException('Доступ запрещён.', 403);
        }

        return $this->mapOrder($order);
    }

    /**
     * Преобразует проекцию заказа в клиентский DTO.
     */
    private function mapOrder(FoodOrderRecord $order): OrderDto
    {
        return new OrderDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurantId,
            restaurantName: (string) ($order->restaurantName ?? ''),
            itemsTotal: $this->formatMoney($order->itemsTotal),
            deliveryApplicable: $order->deliveryCost !== null,
            deliveryCost: $order->deliveryCost !== null
                ? $this->formatMoney($order->deliveryCost)
                : null,
            total: $this->formatMoney($order->total),
            deliveryAddress: $order->deliveryAddress,
            deliveryDate: $order->deliveryDate,
            itemsSnapshot: $order->itemsSnapshot,
            createdAt: $order->createdAt,
        );
    }

    /**
     * Форматирует денежную сумму.
     */
    private function formatMoney(mixed $value): string
    {
        return $this->moneyFormatter->format((float) $value);
    }
}
