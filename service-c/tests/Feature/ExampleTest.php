<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** Эндпоинт данных шлюза возвращает пользователя. */
    public function test_gateway_authenticated_data_endpoint_returns_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->getJson('/api/data');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }
}
