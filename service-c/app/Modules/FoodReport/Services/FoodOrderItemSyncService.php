<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\FoodReport\Contracts\FoodOrderItemSyncServiceInterface;
use App\Modules\FoodReport\Contracts\FoodOrderItemWriteRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Синхронизация max_food_order_items только для confirmed-заказов (вариант B).
 */
final class FoodOrderItemSyncService implements FoodOrderItemSyncServiceInterface
{
    public function __construct(
        private readonly FoodOrderItemWriteRepositoryInterface $itemWriteRepository,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
        private readonly LoggerInterface $logger,
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
     *
     * @throws FoodDomainException если deliveryDate пуст и createdAt невалиден
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
            throw new FoodDomainException(sprintf(
                'Не удалось определить дату отчёта для заказа #%d: невалидный createdAt и пустой deliveryDate.',
                $order->id,
            ));
        }
    }

    /**
     * Строки snapshot с одним dish_id (или null:{dish_name}) схлопываются:
     * quantity и line_total суммируются, dish_name/unit_price — с последней строки группы.
     *
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
        /** @var array<string, array{
         *     restaurant_id: int,
         *     report_date: string,
         *     dish_id: int|null,
         *     dish_name: string,
         *     unit_price: string|float|int,
         *     quantity: int,
         *     line_total: string
         * }> $grouped
         */
        $grouped = [];

        foreach ($order->itemsSnapshot as $line) {
            if (! is_array($line)) {
                continue;
            }

            $dishName = trim((string) ($line['dish_name'] ?? ''));
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($dishName === '' || $quantity <= 0) {
                $this->logger->warning(
                    'Пропуск строки items_snapshot при sync: пустой dish_name или quantity<=0.',
                    [
                        'order_id' => $order->id,
                        'dish_name' => $dishName,
                        'quantity' => $quantity,
                    ],
                );

                continue;
            }

            $rawDishId = $line['dish_id'] ?? null;
            $dishId = $rawDishId !== null && $rawDishId !== '' ? (int) $rawDishId : null;
            $key = $dishId !== null ? (string) $dishId : 'null:'.$dishName;
            $lineTotal = $this->moneyFormatter->format($line['line_total'] ?? '0.00');
            $unitPrice = $line['unit_price'] ?? '0.00';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'restaurant_id' => $order->restaurantId,
                    'report_date' => $reportDate,
                    'dish_id' => $dishId,
                    'dish_name' => $dishName,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];

                continue;
            }

            $grouped[$key]['quantity'] += $quantity;
            // Сумма в копейках (bcmath в runtime недоступен).
            $grouped[$key]['line_total'] = $this->moneyFormatter->formatCents(
                $this->moneyFormatter->toCents($grouped[$key]['line_total'])
                + $this->moneyFormatter->toCents($lineTotal),
            );
            $grouped[$key]['dish_name'] = $dishName;
            $grouped[$key]['unit_price'] = $unitPrice;
        }

        return array_values($grouped);
    }
}
