<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Блокировка и проверка eligibility ручного заказа «Черновик после сканирования».
 */
class DraftAfterScanningOrderLocator
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
    ) {}

    /**
     * Блокирует ручной заказ и проверяет статус «Черновик после сканирования».
     *
     * @throws FoodDomainException
     */
    public function lockEligibleOrder(int $orderId): FoodOrderRecord
    {
        $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

        if ($order === null || ! $order->isManual) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        if ($order->status !== OrderStatus::DraftAfterScanning) {
            throw new FoodDomainException(
                'Действие доступно только для заказа в статусе «Черновик после сканирования».',
                422,
            );
        }

        return $order;
    }
}
