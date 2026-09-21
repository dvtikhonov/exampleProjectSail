<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;

/**
 * Сборка текста MAX-уведомления о новой заявке в UI Stand.
 */
interface FoodOrderUiStandNewRequestMaxMessageBuilderInterface
{
    /**
     * Собирает текст уведомления о новой заявке с учётом лимита символов.
     */
    public function build(
        OrderDto $order,
        MaxUserDisplayDto $customer,
        int $maxTextLength = 4000,
    ): string;
}
