<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Тонкий aggregator DI Food-слоя (меню, корзина, заказы, review, photo-text).
 *
 * Конкретные привязки — в FoodCart/Menu/Order/PhotoText ServiceProvider.
 */
class FoodServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует доменные Food-провайдеры.
     */
    public function register(): void
    {
        $this->app->register(FoodCartServiceProvider::class);
        $this->app->register(FoodMenuServiceProvider::class);
        $this->app->register(FoodOrderServiceProvider::class);
        $this->app->register(FoodPhotoTextServiceProvider::class);
    }
}
