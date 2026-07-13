<?php

namespace App\Providers;

use App\Contracts\AuthServiceInterface;
use App\Contracts\LoginRateLimiterInterface;
use App\Contracts\SessionManagerInterface;
use App\Contracts\TaskAuthorizerInterface;
use App\Contracts\TaskRepositoryInterface;
use App\Contracts\TaskServiceInterface;
use App\Contracts\UserRepositoryInterface;
use App\Contracts\WebAuthenticatorInterface;
use App\Models\Task;
use App\Policies\TaskPolicy;
use App\Repositories\Task\EloquentTaskRepository;
use App\Repositories\User\EloquentUserRepository;
use App\Services\Auth\AuthService;
use App\Services\Auth\LaravelSessionManager;
use App\Services\Auth\LaravelWebAuthenticator;
use App\Services\Auth\LoginRateLimiter;
use App\Services\Task\LaravelTaskAuthorizer;
use App\Services\Task\TaskService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Базовый service provider каркаса service-g (Todo API). */
class AppServiceProvider extends ServiceProvider
{
    /** Регистрация DI-привязок приложения. */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);
        $this->app->bind(TaskAuthorizerInterface::class, LaravelTaskAuthorizer::class);
        $this->app->bind(TaskServiceInterface::class, TaskService::class);
        $this->app->bind(SessionManagerInterface::class, LaravelSessionManager::class);
        $this->app->bind(WebAuthenticatorInterface::class, LaravelWebAuthenticator::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(LoginRateLimiterInterface::class, LoginRateLimiter::class);
    }

    /** Инициализация приложения после регистрации сервисов. */
    public function boot(): void
    {
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
