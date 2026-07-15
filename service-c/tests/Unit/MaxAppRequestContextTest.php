<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaxAppRequestContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class MaxAppRequestContextTest extends TestCase
{
    /** Определяет публичный туннель на поддомене fxtun. */
    public function test_is_public_tunnel_request_on_fxtun_subdomain(): void
    {
        config([
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $request = Request::create(
            'https://exampleprojectsail.fxtun.dev/max-app',
            'GET',
            server: ['HTTP_HOST' => 'exampleprojectsail.fxtun.dev'],
        );

        $this->assertTrue(MaxAppRequestContext::isPublicTunnelRequest($request));
    }

    /** Не считает localhost публичным туннелем. */
    public function test_is_not_public_tunnel_request_on_localhost(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertFalse(MaxAppRequestContext::isPublicTunnelRequest($request));
    }
}
