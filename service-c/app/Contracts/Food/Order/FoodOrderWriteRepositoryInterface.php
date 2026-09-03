<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;

/**
 * Запись и блокирующее чтение заказов еды MAX mini-app.
 */
interface FoodOrderWriteRepositoryInterface
{
    /**
     * Создаёт заказ еды.
     */
    public function create(FoodOrderCreateCommand $command): FoodOrderRecord;

    /**
     * Находит заказ по ID с блокировкой строки (SELECT … FOR UPDATE).
     */
    public function findByIdForUpdate(int $id): ?FoodOrderRecord;

    /**
     * Обновляет заказ еды.
     */
    public function update(FoodOrderRecord $order, FoodOrderUpdateCommand $command): FoodOrderRecord;

    /**
     * Удаляет заказ еды. Сообщения чата и chat_reads удаляются каскадно.
     */
    public function delete(FoodOrderRecord $order): void;
}
