<?php

declare(strict_types=1);

namespace App\Services\Food;

class FoodMoneyFormatter
{
    public function format(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
