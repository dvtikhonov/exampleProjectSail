<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\OrderItemsSnapshotDto;
use App\Models\CartItem;
use App\Models\Dish;
use Illuminate\Support\Collection;

/**
 * Построение снимка позиций заказа из позиций корзины или блюд каталога.
 */
class OrderItemsSnapshotBuilder
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
    ) {}

    /**
     * Формирует items_snapshot и сумму блюд из позиций корзины.
     *
     * @param  Collection<int, CartItem>  $items
     */
    public function build(Collection $items): OrderItemsSnapshotDto
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = [
                'dish' => $item->dish,
                'quantity' => (int) $item->quantity,
                'combo_ref' => $item->combo_ref,
                'combo_partner_dish_id' => $item->combo_partner_dish_id !== null
                    ? (int) $item->combo_partner_dish_id
                    : null,
            ];
        }

        return $this->buildFromDishes($lines);
    }

    /**
     * Формирует items_snapshot и сумму блюд из актуальных блюд каталога.
     *
     * @param  list<array{
     *     dish: Dish,
     *     quantity: int,
     *     combo_ref?: string|null,
     *     combo_partner_dish_id?: int|null
     * }>  $lines
     */
    public function buildFromDishes(array $lines): OrderItemsSnapshotDto
    {
        $itemsSnapshot = [];
        $itemsTotal = 0.0;

        foreach ($lines as $line) {
            $dish = $line['dish'];
            $quantity = (int) $line['quantity'];
            $unitPrice = (float) $dish->price;
            $lineTotal = $unitPrice * $quantity;
            $itemsTotal += $lineTotal;

            $snapshotItem = [
                'dish_id' => (int) $dish->id,
                'dish_name' => $dish->name,
                'unit_price' => $this->moneyFormatter->format($unitPrice),
                'quantity' => $quantity,
                'line_total' => $this->moneyFormatter->format($lineTotal),
                'image_url' => $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->image_url),
            ];

            $comboRef = $line['combo_ref'] ?? null;

            if ($comboRef !== null) {
                $partnerId = $line['combo_partner_dish_id'] ?? null;
                $snapshotItem['combo_ref'] = $comboRef;
                $snapshotItem['combo_partner_dish_ids'] = $partnerId !== null
                    ? [(int) $partnerId]
                    : [];
            }

            $itemsSnapshot[] = $snapshotItem;
        }

        return new OrderItemsSnapshotDto(
            itemsSnapshot: $itemsSnapshot,
            itemsTotal: $itemsTotal,
        );
    }
}
