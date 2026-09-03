<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Выборка заказов для административного API проверки.
 */
interface AdminOrderQueryServiceInterface
{
    /**
     * Возвращает строковые значения активных ролей администратора.
     *
     * @return list<string>
     */
    public function activeRoleValues(MaxUserIdentity $admin): array;

    /**
     * Возвращает постраничный список заказов для админского API по scope и статусу.
     *
     * @return array{
     *     orders: list<\App\DTO\Food\Order\AdminOrderListItemDto>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     *
     * @throws FoodDomainException
     */
    public function list(MaxUserIdentity $admin, string $scope, string $status, int $perPage): array;

    /**
     * Возвращает детальные данные заказа для админского API.
     *
     * @throws FoodDomainException
     */
    public function detail(MaxUserIdentity $admin, int $orderId, string $scope): AdminOrderDetailDto;

    /**
     * Строит детальный DTO заказа по проекции (с перезагрузкой).
     *
     * @throws FoodDomainException
     */
    public function detailFromRecord(FoodOrderRecord $order): AdminOrderDetailDto;
}
