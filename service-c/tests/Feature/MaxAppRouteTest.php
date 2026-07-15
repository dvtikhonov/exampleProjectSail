<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\MaxLocalDevInitData;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class MaxAppRouteTest extends TestCase
{
    /** MAX app возвращает HTML Content-Type на хосте fxtun. */
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

    /** MAX app сохраняет HTML Content-Type на localhost. */
    public function test_max_app_keeps_html_content_type_on_localhost(): void
    {
        $response = $this->get('/max-app', [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }

    /** MAX app внедряет local-dev initData, когда это включено. */
    public function test_max_app_injects_local_dev_init_data_when_enabled(): void
    {
        Config::set('max.bot_access_token', 'route-test-bot-token');
        Config::set('max.local_dev_init_data', true);
        Config::set('max.webhook.url', '');

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertNotNull(MaxLocalDevInitData::build($request));

        $response = $this->get('http://127.0.0.1:8083/max-app');

        $response->assertOk();
        $response->assertViewHas(
            'localDevInitData',
            static fn (?string $value): bool => is_string($value) && str_contains($value, 'auth_date='),
        );
        $response->assertSee('window.__MAX_DEV_INIT_DATA__', false);
    }

    /** MAX app не внедряет local-dev initData, когда это выключено. */
    public function test_max_app_does_not_inject_local_dev_init_data_when_disabled(): void
    {
        config([
            'app.env' => 'local',
            'max.bot_access_token' => 'route-test-bot-token',
            'max.local_dev_init_data' => false,
        ]);

        $response = $this->get('/max-app', [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $response->assertOk();
        $response->assertDontSee('window.__MAX_DEV_INIT_DATA__', false);
    }
}
