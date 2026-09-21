<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Exceptions\Food\FoodDomainException;

/**
 * Детальная выборка заказа для административного API проверки.
 */
interface AdminOrderDetailQueryServiceInterface
{
    /**
     * Возвращает детальные данные заказа для админского API.
     *
     * @throws FoodDomainException
     */
    public function detail(
        MaxUserIdentity $admin,
        int $orderId,
        AdminOrderListScope $scope,
    ): AdminOrderDetailDto;

    /**
     * Строит детальный DTO заказа по проекции (с перезагрузкой).
     *
     * @throws FoodDomainException
     */
    public function detailFromRecord(FoodOrderRecord $order): AdminOrderDetailDto;
}
