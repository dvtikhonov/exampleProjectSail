<?php

declare(strict_types=1);

namespace Tests\Unit;

use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Tests\TestCase;

/**
 * Биндинг MaxMessengerClientInterface и нормализация MAX_MESSENGER_DRIVER.
 */
class MaxMessengerDriverBindingTest extends TestCase
{
    /** Unquoted null в .env Laravel отдаёт как PHP null — должен быть Null-клиент. */
    public function test_null_php_driver_resolves_null_client(): void
    {
        config(['max.messenger_driver' => null]);

        $this->assertInstanceOf(
            NullMaxMessengerClient::class,
            $this->app->make(MaxMessengerClientInterface::class),
        );
    }

    /** Строка "null" → Null-клиент. */
    public function test_null_string_driver_resolves_null_client(): void
    {
        config(['max.messenger_driver' => 'null']);

        $this->assertInstanceOf(
            NullMaxMessengerClient::class,
            $this->app->make(MaxMessengerClientInterface::class),
        );
    }

    /** http → HTTP-клиент. */
    public function test_http_driver_resolves_http_client(): void
    {
        config(['max.messenger_driver' => 'http']);

        $this->assertInstanceOf(
            HttpMaxMessengerClient::class,
            $this->app->make(MaxMessengerClientInterface::class),
        );
    }
}
