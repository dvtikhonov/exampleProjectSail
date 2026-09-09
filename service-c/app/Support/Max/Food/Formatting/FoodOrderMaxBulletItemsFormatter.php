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
        private readonly FoodOrderMaxTextAssembler $textAssembler,
    ) {}

    /**
     * Собирает секцию позиций заказа для сообщения.
     *
     * @param  list<array{dish_name: string, quantity: int, line_total: string}>  $items
     */
    public function buildItemsSection(array $items, int $includedCount, int $remaining): string
    {
        $lines = [];

        if ($includedCount > 0) {
            $lines = $this->formatItemsLines(array_slice($items, 0, $includedCount));
        }

        if ($remaining > 0) {
            $lines[] = $this->textAssembler->buildTruncationSuffix($remaining);
        }

        return implode("\n", $lines);
    }

    /**
     * Форматирует строки позиций заказа.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public function formatItemsLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = sprintf(
                '• %s × %d — %s ₽',
                (string) $item['dish_name'],
                (int) $item['quantity'],
                (string) $item['line_total'],
            );

            $comboLabel = $this->comboResolver->formatComboLabel($item, $items);

            if ($comboLabel !== null) {
                $lines[] = '  '.$comboLabel;
            }
        }

        return $lines;
    }
}
