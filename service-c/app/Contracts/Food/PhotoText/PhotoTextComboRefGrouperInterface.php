<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\DTO\Food\PhotoText\PhotoTextComboRefGroupDto;

/**
 * Группировка позиций агента PhotoText по combo_ref (без split названия по «/»).
 */
interface PhotoTextComboRefGrouperInterface
{
    /**
     * Собирает одиночные позиции и пары combo_ref; битые группы — Unresolved.
     *
     * @param  list<PhotoTextAgentItemDto>  $items
     * @return list<PhotoTextComboRefGroupDto>
     */
    public function group(array $items): array;
}
