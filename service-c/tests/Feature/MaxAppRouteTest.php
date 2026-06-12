<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
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
            'app.url' => 'https://exampleprojectsail.fxtun.dev',
            'max.webhook.url' => 'https://exampleprojectsail.fxtun.dev/api/webhooks/max',
        ]);

        $response = $this->get('https://exampleprojectsail.fxtun.dev/max-app', [
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app page requested');
        $this->assertTrue($log->context['is_tunnel']);
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
