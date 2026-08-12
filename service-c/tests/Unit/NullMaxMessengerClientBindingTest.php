<?php

declare(strict_types=1);

namespace Tests\Unit;

use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Tests\TestCase;

class NullMaxMessengerClientBindingTest extends TestCase
{
    /** При MAX_MESSENGER_DRIVER=null контейнер отдаёт NullMaxMessengerClient. */
    public function test_resolves_null_client_when_driver_is_null(): void
    {
        config(['max.messenger_driver' => 'null']);

        $client = $this->app->make(MaxMessengerClientInterface::class);

        $this->assertInstanceOf(NullMaxMessengerClient::class, $client);
    }

    /** По умолчанию (http) контейнер отдаёт HttpMaxMessengerClient. */
    public function test_resolves_http_client_when_driver_is_http(): void
    {
        config(['max.messenger_driver' => 'http']);

        $client = $this->app->make(MaxMessengerClientInterface::class);

        $this->assertInstanceOf(HttpMaxMessengerClient::class, $client);
    }
}
