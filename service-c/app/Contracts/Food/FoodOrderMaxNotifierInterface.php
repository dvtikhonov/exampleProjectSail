<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderDto;
use App\Models\MaxUser;

interface FoodOrderMaxNotifierInterface
{
    public function notify(OrderDto $order, MaxUser $maxUser): void;
}
