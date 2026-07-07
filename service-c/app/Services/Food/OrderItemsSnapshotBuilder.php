<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\OrderItemsSnapshotDto;
use App\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * Построение снимка позиций заказа из позиций корзины.
 */
class OrderItemsSnapshotBuilder
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
    ) {}

    /**
     * Формирует items_snapshot и сумму блюд.
     *
     * @param  Collection<int, CartItem>  $items
     */
    public function build(Collection $items): OrderItemsSnapshotDto
    {
        $itemsSnapshot = [];
        $itemsTotal = 0.0;

        foreach ($items as $item) {
            $unitPrice = (float) $item->dish->price;
            $lineTotal = $unitPrice * $item->quantity;
            $itemsTotal += $lineTotal;

            $snapshotItem = [
                'dish_id' => (int) $item->dish_id,
                'dish_name' => $item->dish->name,
                'unit_price' => $this->moneyFormatter->format($unitPrice),
                'quantity' => (int) $item->quantity,
                'line_total' => $this->moneyFormatter->format($lineTotal),
                'image_url' => $this->imageUrlResolver->resolvePublicUrl($item->dish_id, $item->dish->image_url),
            ];

            if ($item->combo_ref !== null) {
                $snapshotItem['combo_ref'] = $item->combo_ref;
                $snapshotItem['combo_partner_dish_ids'] = $item->combo_partner_dish_id !== null
                    ? [(int) $item->combo_partner_dish_id]
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
