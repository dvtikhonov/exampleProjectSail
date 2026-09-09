<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Order\AdminOrderQueryServiceInterface;
use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\AdminOrderListStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;

/**
 * Facade: выборка заказов для административного API проверки.
 *
 * Делегирует в {@see AdminOrderListQueryService} и {@see AdminOrderDetailQueryService}.
 */
class AdminOrderQueryService implements AdminOrderQueryServiceInterface
{
    public function __construct(
        private readonly AdminOrderListQueryService $listQueryService,
        private readonly AdminOrderDetailQueryService $detailQueryService,
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
     * {@inheritDoc}
     *
     * @throws FoodDomainException
     */
    public function list(
        MaxUserIdentity $admin,
        AdminOrderListScope $scope,
        AdminOrderListStatus $status,
        int $perPage,
    ): array {
        return $this->listQueryService->list($admin, $scope, $status, $perPage);
    }

    /**
     * {@inheritDoc}
     *
     * @throws FoodDomainException
     */
    public function detail(
        MaxUserIdentity $admin,
        int $orderId,
        AdminOrderListScope $scope,
    ): AdminOrderDetailDto {
        return $this->detailQueryService->detail($admin, $orderId, $scope);
    }

    /**
     * {@inheritDoc}
     *
     * @throws FoodDomainException
     */
    public function detailFromRecord(FoodOrderRecord $order): AdminOrderDetailDto
    {
        return $this->detailQueryService->detailFromRecord($order);
    }
}
