<?php

declare(strict_types=1);

namespace App\Validator;

use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

/** Допустимые значения статуса торговой точки для Symfony Choice constraint. */
final class SalesOutletStatusChoices
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(SalesOutletStatus::cases(), 'value');
    }
}
