<?php

declare(strict_types=1);

/**
 * Настройки домена еды (каталог, кэш меню и т.п.).
 */
return [
    /**
     * TTL safety-net для кэша каталога (рестораны / меню).
     * Основной сброс — bump версии через MenuCatalogCacheInvalidator.
     */
    'catalog_cache_ttl_seconds' => (int) env('FOOD_CATALOG_CACHE_TTL', 600),

    /**
     * Включение кэша каталога (отключить для отладки / сравнения load-тестов).
     */
    'catalog_cache_enabled' => filter_var(
        env('FOOD_CATALOG_CACHE_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),
];
