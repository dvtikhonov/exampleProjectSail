<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Проверяет доступность health-check эндпоинта /up. */
    public function test_health_endpoint_is_available(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    /** Корневой JSON-эндпоинт содержит ссылки на auth API. */
    public function test_root_endpoint_includes_login(): void
    {
        $response = $this->getJson('/');

        $response->assertOk()
            ->assertJson([
                'service' => 'service-g',
                'api' => '/api',
                'login' => '/api/login',
            ])
            ->assertJsonStructure([
                'service',
                'frontend',
                'api',
                'login',
                'register',
                'user',
                'login_page',
                'register_page',
                'test_login_page',
                'test_register_page',
            ]);
    }

    /** Тестовая Blade-страница входа отдаётся без ошибок. */
    public function test_login_page_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Тестовый вход', false);
        $response->assertSee('Нет аккаунта? Зарегистрироваться', false);
    }

    /** Тестовая Blade-страница регистрации отдаётся без ошибок. */
    public function test_register_page_is_available(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Регистрация', false);
        $response->assertSee('Зарегистрироваться', false);
    }
}
