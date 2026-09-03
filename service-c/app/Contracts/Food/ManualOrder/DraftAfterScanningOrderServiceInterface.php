<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\DTO\Food\ManualOrder\DraftAfterScanningMoveToCartResultDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Действия менеджера с ручным заказом в статусе «Черновик после сканирования».
 */
interface DraftAfterScanningOrderServiceInterface
{
    /**
     * Переводит заказ в «Выполнен» (confirmed), approves все этапы проверки
     * и уведомляет оформившего (created_by_max_user_id).
     *
     * @throws FoodDomainException
     */
    public function complete(int $orderId, MaxUserIdentity $manager): FoodOrderRecord;

    /**
     * Переносит позиции снимка в ручную корзину клиента и удаляет заказ.
     *
     * @throws FoodDomainException
     */
    public function moveToCart(int $orderId, MaxUserIdentity $manager): DraftAfterScanningMoveToCartResultDto;

    /**
     * Удаляет заказ без восстановления.
     *
     * @throws FoodDomainException
     */
    public function delete(int $orderId, MaxUserIdentity $manager): void;
}
