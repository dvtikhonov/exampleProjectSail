<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\DTO\Food\PhotoText\PhotoTextPlacementResultDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Сверка и оформление ручного заказа PhotoText в рамках restaurant_id.
 */
interface PhotoTextManualOrderPlacementServiceInterface
{
    /**
     * Точный матч канонических имён в блюдах restaurant_id; комбо только по combo_ref.
     *
     * @param  list<PhotoTextAgentItemDto>  $items
     *
     * @throws FoodDomainException клиент 0 или >1
     */
    public function match(string $customerQuery, int $restaurantId, array $items): PhotoTextPlacementResultDto;

    /**
     * Сверка и оформление matched в ручную корзину с delivery_date из order_date.
     * Заказ создаётся в статусе draft_after_scanning. Пустой matched — отчёт без order_id (контроллер 422).
     *
     * @param  list<PhotoTextAgentItemDto>  $items
     *
     * @throws FoodDomainException клиент 0/>1, менеджер, корзина, submit
     */
    public function place(
        string $customerQuery,
        string $orderDate,
        int $restaurantId,
        array $items,
    ): PhotoTextPlacementResultDto;
}
