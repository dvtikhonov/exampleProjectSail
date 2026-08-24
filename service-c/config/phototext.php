<?php

declare(strict_types=1);

/**
 * Настройки агента Cursor для оформления заказов PhotoText.
 */
return [
    /**
     * Токен заголовка X-PhotoText-Token (сравнение через hash_equals).
     */
    'agent_token' => (string) env('PHOTOTEXT_AGENT_TOKEN', ''),

    /**
     * max_user_id менеджера заказа (created_by) с ролью max_manager.
     * Ресторан в env не фиксируется — агент передаёт restaurant_id.
     */
    'manager_max_user_id' => (int) env('PHOTOTEXT_MANAGER_MAX_USER_ID', 0),
];
