<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderCompositionSnapshotDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Сборка items_snapshot и пересчёт итогов состава заказа из dish_id/qty/combo.
 */
interface OrderCompositionSnapshotBuilderInterface
{
    /**
     * Строит снимок позиций из актуального каталога и пересчитывает суммы.
     *
     * Блюда из $existingDishIds (уже в текущем items_snapshot заказа) допускается
     * сохранять даже при is_available=false — иначе нельзя менять только количество.
     *
     * @param  list<array{
     *     dish_id: int,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>  $items
     * @param  list<int>  $existingDishIds
     *
     * @throws FoodDomainException
     */
    public function build(
        int $restaurantId,
        MaxUser $customer,
        array $items,
        array $existingDishIds = [],
    ): OrderCompositionSnapshotDto;
}
