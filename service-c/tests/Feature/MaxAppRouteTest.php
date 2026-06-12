<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MaxAppRouteTest extends TestCase
{
    public function test_max_app_returns_html_content_type_on_fxtun_host(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        config([
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $response = $this->get('/max-app', [
            'HTTP_HOST' => 'exampleprojectsail.fxtun.dev',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $this->assertCount(1, $captured);
        $this->assertSame('MAX mini-app page requested', $captured[0]->message);
        $this->assertTrue($captured[0]->context['is_tunnel']);
    }

    public function test_max_app_keeps_html_content_type_on_localhost(): void
    {
        $response = $this->get('/max-app', [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }
}
