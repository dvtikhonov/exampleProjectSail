<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Пометки комбо в items_snapshot заказа: partner name по combo_ref и combo_partner_dish_ids.
 */
class OrderSnapshotComboResolver
{
    /**
     * Проверяет, является ли позиция снимка заказа комбо.
     *
     * @param  array<string, mixed>  $item
     */
    public function isComboSnapshotItem(array $item): bool
    {
        return isset($item['combo_ref']) && $item['combo_ref'] !== null && $item['combo_ref'] !== '';
    }

    /**
     * Возвращает название парного блюда комбо из снимка.
     *
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function getComboPartnerName(array $item, array $itemsSnapshot): ?string
    {
        if (! $this->isComboSnapshotItem($item)) {
            return null;
        }

        $partnerIds = $item['combo_partner_dish_ids'] ?? [];

        if (! is_array($partnerIds)) {
            $partnerIds = [];
        }

        foreach ($partnerIds as $partnerId) {
            $partnerInSameCombo = $this->findSnapshotItem(
                $itemsSnapshot,
                static fn (array $other): bool => ($other['combo_ref'] ?? null) === $item['combo_ref']
                    && ($other['dish_id'] ?? null) === $partnerId,
            );

            if ($partnerInSameCombo !== null) {
                $name = trim((string) ($partnerInSameCombo['dish_name'] ?? ''));

                if ($name !== '') {
                    return $name;
                }
            }

            $anyWithDishId = $this->findSnapshotItem(
                $itemsSnapshot,
                static fn (array $other): bool => ($other['dish_id'] ?? null) === $partnerId,
            );

            if ($anyWithDishId !== null) {
                $name = trim((string) ($anyWithDishId['dish_name'] ?? ''));

                if ($name !== '') {
                    return $name;
                }
            }
        }

        $sibling = $this->findSnapshotItem(
            $itemsSnapshot,
            static fn (array $other): bool => ($other['combo_ref'] ?? null) === $item['combo_ref']
                && ($other['dish_id'] ?? null) !== ($item['dish_id'] ?? null),
        );

        if ($sibling === null) {
            return null;
        }

        $name = trim((string) ($sibling['dish_name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * Форматирует подпись комбо-позиции.
     *
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function formatComboLabel(array $item, array $itemsSnapshot): ?string
    {
        if (! $this->isComboSnapshotItem($item)) {
            return null;
        }

        $partnerName = $this->getComboPartnerName($item, $itemsSnapshot);

        if ($partnerName !== null) {
            return sprintf('Входит в комбо: %s', $partnerName);
        }

        return 'Входит в комбо';
    }

    /**
     * Находит позицию в снимке заказа по идентификатору блюда.
     *
     * @param  list<array<string, mixed>>  $itemsSnapshot
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return array<string, mixed>|null
     */
    private function findSnapshotItem(array $itemsSnapshot, callable $predicate): ?array
    {
        foreach ($itemsSnapshot as $other) {
            if (! is_array($other)) {
                continue;
            }

            if ($predicate($other)) {
                return $other;
            }
        }

        return null;
    }
}
