<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use App\Support\Food\Composition\OrderSnapshotComboResolver;

/**
 * Bullet-форматирование позиций заказа для MAX-уведомлений.
 */
final class FoodOrderMaxBulletItemsFormatter
{
    public function __construct(
        private readonly OrderSnapshotComboResolver $comboResolver,
        private readonly FoodOrderMaxMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * Форматирует строки позиций заказа (комбо — одной строкой «блюдо1 / блюдо2»).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public function formatItemsLines(array $items): array
    {
        $lines = [];
        $groups = $this->comboResolver->groupSnapshotItems($items);

        foreach ($groups as $group) {
            if (($group['type'] ?? '') === 'combo') {
                $line = $this->formatComboLine(
                    $group['items'],
                    (int) ($group['quantity'] ?? 0),
                );
            } else {
                $item = $group['items'][0] ?? null;

                if (! is_array($item)) {
                    continue;
                }

                $line = $this->formatSingleItemLine($item);
            }

            if ($line === null) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Форматирует обычную позицию.
     *
     * @param  array<string, mixed>  $item
     */
    private function formatSingleItemLine(array $item): ?string
    {
        $name = trim((string) ($item['dish_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return sprintf(
            '• %s × %d — %s ₽',
            $name,
            (int) ($item['quantity'] ?? 0),
            $this->moneyFormatter->formatMoneyAmount($item['line_total'] ?? null),
        );
    }

    /**
     * Форматирует комбо: «• блюдо 1 / блюдо 2 × N — сумма ₽».
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function formatComboLine(array $items, int $quantity): ?string
    {
        if ($items === []) {
            return null;
        }

        $names = [];
        $lineTotalSum = '0';

        foreach ($items as $item) {
            $name = trim((string) ($item['dish_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $names[] = $name;
            $lineTotalSum = bcadd(
                $lineTotalSum,
                $this->moneyFormatter->formatMoneyAmount($item['line_total'] ?? null),
                2,
            );
        }

        if ($names === []) {
            return null;
        }

        return sprintf(
            '• %s × %d — %s ₽',
            implode(' / ', $names),
            $quantity,
            $lineTotalSum,
        );
    }
}
