<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxOrderNotificationConfig;

/**
 * Поставщик конфигурации уведомлений о заказах в MAX.
 */
interface MaxOrderNotificationConfigProviderInterface
{
    /**
     * Возвращает настройки получателей и лимитов текста.
     */
    public function config(): MaxOrderNotificationConfig;
}
