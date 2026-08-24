<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\DTO\Food\PhotoText\PhotoTextPlacementResultDto;

/**
 * Матчинг канонических позиций агента PhotoText к блюдам и комбо-парам.
 */
interface PhotoTextDishLineResolverInterface
{
    /**
     * Точный матч канонических имён агента в блюдах restaurant_id; комбо только по combo_ref.
     *
     * @param  list<PhotoTextAgentItemDto>  $items
     */
    public function resolveAgentItems(array $items, int $restaurantId): PhotoTextPlacementResultDto;
}
