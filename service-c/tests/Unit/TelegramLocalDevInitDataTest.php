<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Support\TelegramLocalDevInitData;
use App\Services\Telegram\TelegramWebAppInitDataValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

class TelegramLocalDevInitDataTest extends TestCase
{
    private const BOT_TOKEN = 'local-dev-tg-bot-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'local',
            'telegram.bot_token' => self::BOT_TOKEN,
            'telegram.local_dev_init_data' => true,
            'telegram.miniapp.auth_date_max_age_seconds' => 86_400,
        ]);
    }

    /** Собирает валидные initData для демо Telegram-пользователя на localhost. */
    public function test_builds_valid_init_data_for_demo_user_on_localhost(): void
    {
        config(['telegram.local_dev_user_id' => 777_001]);

        $request = Request::create(
            'http://127.0.0.1:8083/tg-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $initData = TelegramLocalDevInitData::build($request);

        $this->assertNotNull($initData);

        $validator = $this->app->make(TelegramWebAppInitDataValidator::class);
        $dto = $validator->validate((string) $initData);

        $this->assertSame(777_001, $dto->telegramUserId);
        $this->assertSame('Demo', $dto->firstName);
        $this->assertSame('Telegram', $dto->lastName);
    }

    /** Не включает заглушку вне local/testing. */
    public function test_disabled_outside_local_or_testing_env(): void
    {
        config(['app.env' => 'production']);

        $request = Request::create(
            'http://127.0.0.1:8083/tg-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertFalse(TelegramLocalDevInitData::isEnabled($request));
        $this->assertNull(TelegramLocalDevInitData::build($request));
    }

    /** Не включает заглушку на публичном хосте. */
    public function test_disabled_on_non_local_host(): void
    {
        $request = Request::create(
            'https://94-228-117-27.sslip.io/tg-app',
            'GET',
            server: ['HTTP_HOST' => '94-228-117-27.sslip.io'],
        );

        $this->assertFalse(TelegramLocalDevInitData::isEnabled($request));
        $this->assertNull(TelegramLocalDevInitData::build($request));
    }

    /** Не включает заглушку без bot token. */
    public function test_disabled_without_bot_token(): void
    {
        config(['telegram.bot_token' => '']);

        $request = Request::create(
            'http://127.0.0.1:8083/tg-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertFalse(TelegramLocalDevInitData::isEnabled($request));
        $this->assertNull(TelegramLocalDevInitData::build($request));
    }
}
