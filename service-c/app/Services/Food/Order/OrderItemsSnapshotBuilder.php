<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Order\OrderItemsSnapshotDto;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Services\Food\Shared\FoodMoneyFormatter;

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
     * @param  list<CartItemRecord>|iterable<int, CartItemRecord>  $items
     */
    public function build(iterable $items): OrderItemsSnapshotDto
    {
        $lines = [];

        foreach ($items as $item) {
            $dish = $item->dish ?? throw new \LogicException(
                'Для OrderItemsSnapshotBuilder требуется загруженное блюдо в CartItemRecord.',
            );

            $lines[] = [
                'dish' => $dish,
                'quantity' => $item->quantity,
                'combo_ref' => $item->comboRef,
                'combo_partner_dish_id' => $item->comboPartnerDishId,
            ];
        }

        return $this->buildFromDishes($lines);
    }

    /**
     * Формирует items_snapshot и сумму блюд из актуальных блюд каталога.
     *
     * @param  list<array{
     *     dish: DishRecord,
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

            $weightUnit = $dish->weightUnit ?? DishWeightUnit::Gram;

            $snapshotItem = [
                'dish_id' => $dish->id,
                'dish_name' => $dish->name,
                'description' => $this->normalizeDescription($dish->description),
                'weight' => $this->formatWeight($dish->weight),
                'weight_unit' => $weightUnit->value,
                'unit_price' => $this->moneyFormatter->format($unitPrice),
                'quantity' => $quantity,
                'line_total' => $this->moneyFormatter->format($lineTotal),
                'image_url' => $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->imageUrl),
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

    /**
     * Нормализует описание блюда для снимка заказа.
     */
    private function normalizeDescription(mixed $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $normalized = trim((string) $description);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Форматирует вес блюда для снимка заказа.
     */
    private function formatWeight(mixed $weight): ?string
    {
        if ($weight === null || $weight === '') {
            return null;
        }

        return (string) (int) round((float) $weight);
    }
}
