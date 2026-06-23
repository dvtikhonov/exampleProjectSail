<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\DTO\Max\MaxOrderNotificationConfig;
use Illuminate\Contracts\Config\Repository;

/**
 * Чтение настроек уведомлений о заказах из конфигурации Laravel.
 */
class ConfigMaxOrderNotificationConfigProvider implements MaxOrderNotificationConfigProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function config(): MaxOrderNotificationConfig
    {
        $chatIds = array_values(array_map(
            intval(...),
            (array) $this->config->get('max.order_notifications.chat_ids', []),
        ));
        $userIds = array_values(array_map(
            intval(...),
            (array) $this->config->get('max.order_notifications.user_ids', []),
        ));

        return new MaxOrderNotificationConfig(
            chatIds: $chatIds,
            userIds: $userIds,
            maxTextLength: (int) $this->config->get('max.order_notifications.max_text_length', 4000),
        );
    }
}
