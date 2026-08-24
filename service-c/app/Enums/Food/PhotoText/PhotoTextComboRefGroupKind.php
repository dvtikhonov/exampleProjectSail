<?php

declare(strict_types=1);

namespace App\Enums\Food\PhotoText;

/**
 * Вид группы позиций агента PhotoText по combo_ref.
 */
enum PhotoTextComboRefGroupKind: string
{
    case Single = 'single';
    case Pair = 'pair';
    case Unresolved = 'unresolved';
}
