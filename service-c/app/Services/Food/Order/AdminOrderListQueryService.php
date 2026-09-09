<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\DTO\Food\Order\AdminOrderListItemDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\AdminOrderListStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Постраничный список заказов для административного API проверки.
 */
class AdminOrderListQueryService
{
    public function __construct(
        private readonly AdminOrderReviewListResolver $listResolver,
        private readonly OrderMessageRepositoryInterface $orderMessageRepository,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
    ) {}

    /**
     * Возвращает постраничный список заказов для админского API по scope и статусу.
     *
     * @return array{
     *     orders: list<AdminOrderListItemDto>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     *
     * @throws FoodDomainException
     */
    public function list(
        MaxUserIdentity $admin,
        AdminOrderListScope $scope,
        AdminOrderListStatus $status,
        int $perPage,
    ): array {
        $this->assertScopeAccess($admin, $scope);

        $paginator = $this->listResolver->resolve($scope, $status, $perPage);

        /** @var list<FoodOrderRecord> $orders */
        $orders = $paginator->items;

        return [
            'orders' => $this->mapListItems($admin, $orders),
            'meta' => [
                'current_page' => $paginator->currentPage,
                'per_page' => $paginator->perPage,
                'total' => $paginator->total,
                'last_page' => $paginator->lastPage,
            ],
        ];
    }

    /**
     * Проверяет доступ администратора к указанному scope проверки.
     *
     * @throws FoodDomainException
     */
    private function assertScopeAccess(MaxUserIdentity $admin, AdminOrderListScope $scope): void
    {
        if (! $admin->hasAdminRole($scope->requiredRole())) {
            throw new FoodDomainException('Доступ запрещён.', 403);
        }
    }

    /**
     * Преобразует коллекцию заказов в список DTO для админского списка.
     *
     * @param  list<FoodOrderRecord>  $orders
     * @return list<AdminOrderListItemDto>
     */
    private function mapListItems(MaxUserIdentity $admin, array $orders): array
    {
        $orderIds = array_map(
            static fn (FoodOrderRecord $order): int => $order->id,
            $orders,
        );
        $chatStats = $this->orderMessageRepository->getChatStatsForOrders(
            $orderIds,
            $admin->maxUserId,
        );

        return array_map(
            function (FoodOrderRecord $order) use ($chatStats): AdminOrderListItemDto {
                $stats = $chatStats[$order->id] ?? [
                    'last_message_at' => null,
                    'unread_count' => 0,
                ];

                return $this->mapListItem($order, $stats);
            },
            $orders,
        );
    }

    /**
     * Преобразует заказ в DTO элемента админского списка.
     *
     * @param  array{last_message_at: ?string, unread_count: int}  $chatStats
     */
    private function mapListItem(FoodOrderRecord $order, array $chatStats): AdminOrderListItemDto
    {
        return new AdminOrderListItemDto(
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
            itemsTotal: $this->formatMoney($order->itemsTotal),
            deliveryCost: $order->deliveryCost !== null ? $this->formatMoney($order->deliveryCost) : null,
            total: $this->formatMoney($order->total),
            addressReviewStatus: $order->addressReviewStatus->value,
            compositionReviewStatus: $order->compositionReviewStatus->value,
            paymentReviewStatus: $order->paymentReviewStatus->value,
            lastMessageAt: $chatStats['last_message_at'],
            unreadCount: $chatStats['unread_count'],
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
