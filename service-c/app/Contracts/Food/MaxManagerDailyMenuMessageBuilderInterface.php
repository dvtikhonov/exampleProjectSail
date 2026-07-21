<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\DailyMenuLineDto;
use App\DTO\Food\MaxManagerDailyMenuMessagesDto;
use Carbon\CarbonImmutable;

/**
 * Формирование текстов ежедневного меню для max_manager.
 */
interface MaxManagerDailyMenuMessageBuilderInterface
{
    /**
     * Собирает два сообщения: без доставки и с блоком о стоимости доставки.
     *
     * @param  list<DailyMenuLineDto>  $lines
     */
    public function build(CarbonImmutable $menuDate, array $lines): MaxManagerDailyMenuMessagesDto;
}
