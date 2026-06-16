<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesStatefulApiRequests;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use MakesStatefulApiRequests;
    use RefreshDatabase;

    public function test_guest_cannot_access_user_endpoint(): void
    {
        $response = $this->getStatefulJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_user_can_register(): void
    {
        $response = $this->postStatefulJson('/api/register', [
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

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postStatefulJson('/api/login', [
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

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postStatefulJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

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

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsStateful($user)->postStatefulJson('/api/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out.',
            ]);
    }

    public function test_guest_can_logout_idempotently(): void
    {
        $response = $this->postStatefulJson('/api/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out.',
            ]);
    }
}
