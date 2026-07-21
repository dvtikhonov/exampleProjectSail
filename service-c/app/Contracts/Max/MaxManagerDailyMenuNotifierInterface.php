<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Рассылка ежедневного меню пользователям с ролью max_manager.
 */
interface MaxManagerDailyMenuNotifierInterface
{
    /**
     * Отправляет два сообщения о меню на завтра (MSK) каждому активному max_manager.
     *
     * Сначала DM; при ошибке MAX — fallback в MAX_UI_STAND_* (как «Заказ на …»).
     *
     * @return int Количество успешно отправленных сообщений
     */
    public function notify(): int;
}
