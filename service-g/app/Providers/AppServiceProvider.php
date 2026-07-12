<?php

namespace App\Providers;

use App\Contracts\UserRepositoryInterface;
use App\Repositories\User\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/** Базовый service provider каркаса service-g (Todo API). */
class AppServiceProvider extends ServiceProvider
{
    /** Регистрация DI-привязок приложения. */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    /** Инициализация приложения после регистрации сервисов. */
    public function boot(): void
    {
        //
    }
}
