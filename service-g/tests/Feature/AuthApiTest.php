<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesStatefulApiRequests;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use MakesStatefulApiRequests;
    use RefreshDatabase;

    /** Гость не может получить профиль через GET /api/user. */
    public function test_guest_cannot_access_user_endpoint(): void
    {
        $response = $this->getStatefulJson('/api/user');

        $response->assertUnauthorized();
    }

    /** POST /api/auth/register создаёт пользователя и возвращает 201. */
    public function test_user_can_register(): void
    {
        $response = $this->postStatefulJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJson([
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ])
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    /** POST /api/auth/login с верными credentials открывает сессию. */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postStatefulJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    /** Неверный пароль возвращает 422 и не авторизует пользователя. */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postStatefulJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    /** Авторизованный пользователь получает свой профиль через GET /api/user. */
    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsStateful($user)->getStatefulJson('/api/user');

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    /** POST /api/auth/logout завершает сессию авторизованного пользователя. */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsStateful($user)->postStatefulJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out.',
            ]);
    }

    /** После пяти неудачных попыток вход блокируется rate limiter. */
    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postStatefulJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['email']);
        }

        $response = $this->postStatefulJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }
}
