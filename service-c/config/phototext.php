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
     * Токен заголовка X-PhotoText-Write-Token для мутаций (place/apply).
     * Сравнение через hash_equals; пустой или неверный — 401.
     */
    'write_token' => (string) env('PHOTOTEXT_WRITE_TOKEN', ''),

    /**
     * Allow-list max_user_id для write: если >0, активный AI-user
     * (кто включил ai_access) должен совпадать, иначе 403. created_by = active AI-user.
     * 0 / пусто — без ограничения (любой активный max_manager с AI) в local/testing.
     * В production значение >0 обязательно (иначе write-middleware отвечает 503).
     * Ресторан в env не фиксируется — агент передаёт restaurant_id.
     */
    'manager_max_user_id' => (int) env('PHOTOTEXT_MANAGER_MAX_USER_ID', 0),
];
