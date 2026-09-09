<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use App\Support\Food\Composition\OrderSnapshotComboResolver;

/**
 * Форматирование позиций ручного заказа для MAX-уведомлений.
 */
final class FoodOrderMaxManualItemsFormatter
{
    public function __construct(
        private readonly OrderSnapshotComboResolver $comboResolver,
        private readonly FoodOrderMaxItemsExtractor $itemsExtractor,
        private readonly FoodOrderMaxWeightFormatter $weightFormatter,
        private readonly FoodOrderMaxMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * Строки позиций ручного заказа (обычные и комбо) с нумерацией.
     *
     * @param  list<mixed>|array<int, mixed>  $itemsSnapshot
     * @return list<string>
     */
    public function formatManualOrderItemLines(array $itemsSnapshot): array
    {
        $items = $this->itemsExtractor->extractItemsFromSnapshot($itemsSnapshot);
        $groups = $this->comboResolver->groupSnapshotItems($items);
        $lines = [];
        $number = 1;

        foreach ($groups as $group) {
            if (($group['type'] ?? '') === 'combo') {
                $line = $this->formatManualComboLine($number, $group['items'], (int) ($group['quantity'] ?? 0));
            } else {
                $item = $group['items'][0] ?? null;

                if (! is_array($item)) {
                    continue;
                }

                $line = $this->formatManualSingleItemLine($number, $item);
            }

            if ($line === null) {
                continue;
            }

            $lines[] = $line;
            $number++;
        }

        return $lines;
    }

    /**
     * Форматирует обычную позицию ручного заказа.
     *
     * @param  array<string, mixed>  $item
     */
    private function formatManualSingleItemLine(int $number, array $item): ?string
    {
        $name = trim((string) ($item['dish_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $parts = [sprintf('%d. %s', $number, $name)];

        $description = trim((string) ($item['description'] ?? ''));

        if ($description !== '') {
            $parts[0] .= sprintf(' (%s)', $description);
        }

        $weightLabel = $this->weightFormatter->formatWeightLabel($item);

        if ($weightLabel !== null) {
            $parts[0] .= ', '.$weightLabel;
        }

        $parts[0] .= sprintf(
            ' – %sр - %dшт.',
            $this->moneyFormatter->formatRublesAmount($item['unit_price'] ?? null),
            (int) ($item['quantity'] ?? 0),
        );

        return $parts[0];
    }

    /**
     * Форматирует комбо-позицию: «блюдо 1 / блюдо 2, вес1 / вес2 – суммар - Nшт.»
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function formatManualComboLine(int $number, array $items, int $quantity): ?string
    {
        if ($items === []) {
            return null;
        }

        $names = [];
        $weights = [];
        $unitPriceSum = 0.0;

        foreach ($items as $item) {
            $name = trim((string) ($item['dish_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $names[] = $name;
            $weights[] = $this->weightFormatter->formatWeightLabel($item) ?? '';
            $unitPriceSum += (float) ($item['unit_price'] ?? 0);
        }

        if ($names === []) {
            return null;
        }

        $line = sprintf('%d. %s', $number, implode(' / ', $names));

        if ($this->hasAnyNonEmptyWeight($weights)) {
            $line .= ', '.implode(' / ', $weights);
        }

        $line .= sprintf(
            ' – %sр - %dшт.',
            $this->moneyFormatter->formatRublesAmount($unitPriceSum),
            $quantity,
        );

        return $line;
    }

    /**
     * Проверяет, есть ли хотя бы один непустой вес в комбо.
     *
     * @param  list<string>  $weights
     */
    private function hasAnyNonEmptyWeight(array $weights): bool
    {
        foreach ($weights as $weight) {
            if ($weight !== '') {
                return true;
            }
        }

        return false;
    }
}
