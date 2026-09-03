<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Chat\OrderMessageRepositoryInterface;
use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\AdminOrderListItemDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Выборка заказов для административного API проверки.
 */
class AdminOrderQueryService implements AdminOrderQueryServiceInterface
{
    public function __construct(
        private readonly FoodOrderAdminReadRepositoryInterface $foodOrderReadRepository,
        private readonly OrderMessageRepositoryInterface $orderMessageRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * Возвращает строковые значения активных ролей администратора.
     *
     * @return list<string>
     */
    public function activeRoleValues(MaxUserIdentity $admin): array
    {
        return array_map(
            static fn (FoodOrderAdminRole $role): string => $role->value,
            $admin->adminRoles,
        );
    }

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
    public function list(MaxUserIdentity $admin, string $scope, string $status, int $perPage): array
    {
        $this->assertScopeAccess($admin, $scope);

        $paginator = match ($status) {
            'pending' => match ($scope) {
                'address' => $this->foodOrderReadRepository->paginateForAddressReview(
                    OrderReviewStatus::Pending,
                    $perPage,
                ),
                'composition' => $this->foodOrderReadRepository->paginateForCompositionReview(
                    OrderReviewStatus::Pending,
                    $perPage,
                ),
                default => throw new FoodDomainException('Некорректный scope. Используйте address или composition.', 422),
            },
            'all' => $this->foodOrderReadRepository->paginateAll($perPage),
            default => throw new FoodDomainException('Некорректный status. Используйте pending или all.', 422),
        };

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
     * Возвращает детальные данные заказа для админского API.
     *
     * @throws FoodDomainException
     */
    public function detail(MaxUserIdentity $admin, int $orderId, string $scope): AdminOrderDetailDto
    {
        $this->assertScopeAccess($admin, $scope);

        $order = $this->foodOrderReadRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * Строит детальный DTO заказа по проекции (с перезагрузкой).
     *
     * @throws FoodDomainException
     */
    public function detailFromRecord(FoodOrderRecord $order): AdminOrderDetailDto
    {
        $order = $this->foodOrderReadRepository->findById($order->id);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * Проверяет доступ администратора к указанному scope проверки.
     *
     * @throws FoodDomainException
     */
    private function assertScopeAccess(MaxUserIdentity $admin, string $scope): void
    {
        $role = $this->resolveScopeRole($scope);

        if (! $admin->hasAdminRole($role)) {
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
     * Сопоставляет scope проверки с ролью администратора.
     *
     * @throws FoodDomainException
     */
    private function resolveScopeRole(string $scope): FoodOrderAdminRole
    {
        return match ($scope) {
            'address' => FoodOrderAdminRole::AddressReviewer,
            'composition' => FoodOrderAdminRole::CompositionReviewer,
            default => throw new FoodDomainException('Некорректный scope. Используйте address или composition.', 422),
        };
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
     * Преобразует заказ в детальный админский DTO.
     */
    private function mapDetail(FoodOrderRecord $order): AdminOrderDetailDto
    {
        return new AdminOrderDetailDto(
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
            itemsSnapshot: $order->itemsSnapshot,
            addressReviewStatus: $order->addressReviewStatus->value,
            compositionReviewStatus: $order->compositionReviewStatus->value,
            paymentReviewStatus: $order->paymentReviewStatus->value,
            addressReviewedBy: $order->addressReviewedBy,
            addressReviewedAt: $order->addressReviewedAt,
            addressRejectionComment: $order->addressRejectionComment,
            compositionReviewedBy: $order->compositionReviewedBy,
            compositionReviewedAt: $order->compositionReviewedAt,
            compositionRejectionComment: $order->compositionRejectionComment,
            paymentReviewedBy: $order->paymentReviewedBy,
            paymentReviewedAt: $order->paymentReviewedAt,
            paymentRejectionComment: $order->paymentRejectionComment,
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
