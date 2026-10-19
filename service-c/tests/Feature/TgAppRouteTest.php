<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Support\TelegramLocalDevInitData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TgAppRouteTest extends TestCase
{
    /** /tg-app возвращает HTML на localhost. */
    public function test_tg_app_returns_html_on_localhost(): void
    {
        $response = $this->get('/tg-app', [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $response->assertSee('window.__MESSENGER_CHANNEL__ = \'telegram\'', false);
        $response->assertSee('telegram.org/js/telegram-web-app.js', false);
        $this->assertTrue(
            str_contains($response->getContent(), 'id="tg-app"')
            || str_contains($response->getContent(), 'Фронтенд не собран'),
            'Expected mount #tg-app or assets fallback',
        );
    }

    /** /tg-app внедряет local-dev initData, когда это включено. */
    public function test_tg_app_injects_local_dev_init_data_when_enabled(): void
    {
        Config::set('telegram.bot_token', 'route-test-tg-bot-token');
        Config::set('telegram.local_dev_init_data', true);

        $request = Request::create(
            'http://127.0.0.1:8083/tg-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertNotNull(TelegramLocalDevInitData::build($request));

        $response = $this->get('http://127.0.0.1:8083/tg-app');

        $response->assertOk();
        $response->assertViewHas(
            'localDevInitData',
            static fn (?string $value): bool => is_string($value) && str_contains($value, 'auth_date='),
        );
        $response->assertSee('window.__TELEGRAM_DEV_INIT_DATA__', false);
    }

    /** /tg-app не внедряет local-dev initData, когда это выключено. */
    public function test_tg_app_does_not_inject_local_dev_init_data_when_disabled(): void
    {
        config([
            'app.env' => 'local',
            'telegram.bot_token' => 'route-test-tg-bot-token',
            'telegram.local_dev_init_data' => false,
        ]);

        $response = $this->get('/tg-app', [
            'HTTP_HOST' => '127.0.0.1:8083',
        ]);

        $response->assertOk();
        $response->assertDontSee('window.__TELEGRAM_DEV_INIT_DATA__', false);
    }
}
