<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\DTO\Food\ManualOrder\DraftAfterScanningMoveToCartResultDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;

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
    public function complete(int $orderId, MaxUser $manager): FoodOrder;

    /**
     * Переносит позиции снимка в ручную корзину клиента и удаляет заказ.
     *
     * @throws FoodDomainException
     */
    public function moveToCart(int $orderId, MaxUser $manager): DraftAfterScanningMoveToCartResultDto;

    /**
     * Удаляет заказ без восстановления.
     *
     * @throws FoodDomainException
     */
    public function delete(int $orderId, MaxUser $manager): void;
}
