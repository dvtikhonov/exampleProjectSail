<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Order\OrderItemsSnapshotDto;

/**
 * Построение снимка позиций заказа из позиций корзины или блюд каталога.
 */
interface OrderItemsSnapshotBuilderInterface
{
    /**
     * Формирует items_snapshot и сумму блюд из позиций корзины.
     *
     * @param  list<CartItemRecord>|iterable<int, CartItemRecord>  $items
     */
    public function build(iterable $items): OrderItemsSnapshotDto;

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
    public function buildFromDishes(array $lines): OrderItemsSnapshotDto;
}
