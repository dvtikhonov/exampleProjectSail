<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use App\DTO\Food\Order\OrderDto;

/**
 * Извлечение позиций заказа из items_snapshot для MAX-уведомлений.
 */
final class FoodOrderMaxItemsExtractor
{
    /**
     * Извлекает позиции из снимка состава заказа.
     *
     * @return list<array<string, mixed>>
     */
    public function extractItems(OrderDto $order): array
    {
        return $this->extractItemsFromSnapshot($order->itemsSnapshot);
    }

    /**
     * Извлекает позиции из массива items_snapshot.
     *
     * @param  list<mixed>|array<int, mixed>  $itemsSnapshot
     * @return list<array<string, mixed>>
     */
    public function extractItemsFromSnapshot(array $itemsSnapshot): array
    {
        $items = [];

        foreach ($itemsSnapshot as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }

            $item = [
                'dish_id' => (int) ($snapshot['dish_id'] ?? 0),
                'dish_name' => (string) ($snapshot['dish_name'] ?? ''),
                'description' => isset($snapshot['description']) ? (string) $snapshot['description'] : null,
                'weight' => $snapshot['weight'] ?? null,
                'weight_unit' => $snapshot['weight_unit'] ?? null,
                'quantity' => (int) ($snapshot['quantity'] ?? 0),
                'unit_price' => (string) ($snapshot['unit_price'] ?? '0.00'),
                'line_total' => (string) ($snapshot['line_total'] ?? '0.00'),
            ];

            if (isset($snapshot['combo_ref']) && $snapshot['combo_ref'] !== null && $snapshot['combo_ref'] !== '') {
                $item['combo_ref'] = (string) $snapshot['combo_ref'];
                $item['combo_partner_dish_ids'] = is_array($snapshot['combo_partner_dish_ids'] ?? null)
                    ? $snapshot['combo_partner_dish_ids']
                    : [];
            }

            $items[] = $item;
        }

        return $items;
    }
}
