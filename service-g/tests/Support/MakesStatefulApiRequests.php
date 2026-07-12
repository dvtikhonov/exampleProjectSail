<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Заголовки Origin/Referer для Sanctum stateful API в feature-тестах.
 */
trait MakesStatefulApiRequests
{
    /**
     * POST JSON с заголовками Sanctum stateful (Origin/Referer).
     *
     * @param  array<string, mixed>  $data
     */
    protected function postStatefulJson(string $uri, array $data = []): TestResponse
    {
        return $this->withStatefulApiHeaders()->postJson($uri, $data);
    }

    /** GET JSON с заголовками Sanctum stateful (Origin/Referer). */
    protected function getStatefulJson(string $uri): TestResponse
    {
        return $this->withStatefulApiHeaders()->getJson($uri);
    }

    /** Авторизует пользователя и добавляет stateful-заголовки. */
    protected function actingAsStateful(User $user): static
    {
        return $this
            ->actingAs($user)
            ->withStatefulApiHeaders();
    }

    /** Добавляет Origin/Referer для прохождения Sanctum stateful-проверки. */
    protected function withStatefulApiHeaders(): static
    {
        $origin = rtrim((string) config('app.url'), '/');

        return $this
            ->withHeader('Origin', $origin)
            ->withHeader('Referer', $origin.'/');
    }
}
