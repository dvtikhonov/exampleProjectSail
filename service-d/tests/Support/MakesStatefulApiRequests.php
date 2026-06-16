<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Заголовки Origin/Referer для Sanctum stateful API в feature-тестах.
 */
trait MakesStatefulApiRequests
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function postStatefulJson(string $uri, array $data = []): TestResponse
    {
        return $this->withStatefulApiHeaders()->postJson($uri, $data);
    }

    protected function getStatefulJson(string $uri): TestResponse
    {
        return $this->withStatefulApiHeaders()->getJson($uri);
    }

    protected function actingAsStateful(User $user): static
    {
        return $this
            ->actingAs($user)
            ->withStatefulApiHeaders();
    }

    protected function withStatefulApiHeaders(): static
    {
        $origin = rtrim((string) config('app.url'), '/');

        return $this
            ->withHeader('Origin', $origin)
            ->withHeader('Referer', $origin.'/');
    }
}
