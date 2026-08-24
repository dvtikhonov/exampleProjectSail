<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

use App\Enums\Food\PhotoText\PhotoTextComboRefGroupKind;

/**
 * Группа позиций агента: одиночная, валидная комбо-пара или нерезолвленная combo_ref.
 */
readonly class PhotoTextComboRefGroupDto
{
    /**
     * @param  list<PhotoTextAgentItemDto>  $items
     */
    public function __construct(
        public PhotoTextComboRefGroupKind $kind,
        public array $items,
    ) {}
}
