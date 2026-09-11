<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Modules\FoodReport\Contracts\FoodOrderItemSyncServiceInterface;
use App\Modules\FoodReport\Contracts\FoodOrderItemWriteRepositoryInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Синхронизация max_food_order_items только для confirmed-заказов (вариант B).
 */
final class FoodOrderItemSyncService implements FoodOrderItemSyncServiceInterface
{
    public function __construct(
        private readonly FoodOrderItemWriteRepositoryInterface $itemWriteRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function syncIfConfirmed(FoodOrderRecord $order): void
    {
        if ($order->status !== OrderStatus::Confirmed) {
            $this->itemWriteRepository->deleteByOrderId($order->id);

            return;
        }

        $reportDate = $this->resolveReportDate($order);
        $rows = $this->mapSnapshotToRows($order, $reportDate);

        $this->itemWriteRepository->upsertByOrderId($order->id, $rows);
    }

    /**
     * Ось даты отчёта: delivery_date, иначе DATE(created_at).
     */
    private function resolveReportDate(FoodOrderRecord $order): string
    {
        $deliveryDate = is_string($order->deliveryDate) ? trim($order->deliveryDate) : '';

        if ($deliveryDate !== '') {
            return substr($deliveryDate, 0, 10);
        }

        try {
            return (new DateTimeImmutable($order->createdAt))->format('Y-m-d');
        } catch (Throwable) {
            return (new DateTimeImmutable('now'))->format('Y-m-d');
        }
    }

    /**
     * @return list<array{
     *     restaurant_id: int,
     *     report_date: string,
     *     dish_id: int|null,
     *     dish_name: string,
     *     unit_price: string|float|int,
     *     quantity: int,
     *     line_total: string|float|int
     * }>
     */
    private function mapSnapshotToRows(FoodOrderRecord $order, string $reportDate): array
    {
        $rows = [];

        foreach ($order->itemsSnapshot as $line) {
            if (! is_array($line)) {
                continue;
            }

            $dishName = trim((string) ($line['dish_name'] ?? ''));
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($dishName === '' || $quantity <= 0) {
                continue;
            }

            $dishId = $line['dish_id'] ?? null;

            $rows[] = [
                'restaurant_id' => $order->restaurantId,
                'report_date' => $reportDate,
                'dish_id' => $dishId !== null && $dishId !== '' ? (int) $dishId : null,
                'dish_name' => $dishName,
                'unit_price' => $line['unit_price'] ?? '0.00',
                'quantity' => $quantity,
                'line_total' => $line['line_total'] ?? '0.00',
            ];
        }

        return $rows;
    }
}
