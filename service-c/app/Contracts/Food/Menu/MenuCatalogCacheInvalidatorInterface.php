<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Инвалидация кэша клиентского каталога еды (рестораны / меню).
 */
interface MenuCatalogCacheInvalidatorInterface
{
    /**
     * Сбрасывает весь кэш каталога (bump версии).
     *
     * Реализация не должна пробрасывать сбои store наружу (админ-сохранение уже прошло).
     */
    public function invalidateAll(): void;

    /**
     * Сбрасывает кэш каталога для ресторана (bump версии каталога целиком).
     */
    public function invalidateRestaurant(int $restaurantId): void;
}
