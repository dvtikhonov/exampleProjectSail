<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use Carbon\CarbonImmutable;

/**
 * Рассылка ежедневного меню пользователям с ролью max_manager.
 */
interface MaxManagerDailyMenuNotifierInterface
{
    /**
     * Отправляет два сообщения о меню на дату «Блюда на» каждому активному max_manager.
     *
     * Сначала DM; при ошибке MAX — fallback в MAX_UI_STAND_* (как «Заказ на …»).
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(CarbonImmutable $menuDate): int;
}
