<?php

declare(strict_types=1);

namespace Tests\Feature\UrlShortener;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Breeze-аутентификация для доступа к Filament-панели URL shortener.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_redirected_to_admin_panel(): void
    {
        $response = $this->post('/register', [
            'name' => 'Shortener User',
            'email' => 'shortener@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin');

        $this->assertDatabaseHas('users', [
            'email' => 'shortener@example.com',
        ]);
    }

    public function test_user_can_login_and_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }
}
