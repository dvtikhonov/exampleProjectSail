<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Max\MaxWebAppInitDataValidator;
use App\Support\MaxLocalDevInitData;
use App\Support\MaxWebAppInitDataSigner;
use Illuminate\Http\Request;
use Tests\TestCase;

class MaxLocalDevInitDataTest extends TestCase
{
    private const BOT_TOKEN = 'local-dev-bot-token';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'local',
            'max.bot_access_token' => self::BOT_TOKEN,
            'max.local_dev_init_data' => true,
            'max.miniapp.auth_date_max_age_seconds' => 86_400,
        ]);
    }

    /** Собирает валидные initData для демо VIP-пользователя на localhost. */
    public function test_builds_valid_init_data_for_demo_vip_user_on_localhost(): void
    {
        config(['max.local_dev_user_id' => 1002]);

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $initData = MaxLocalDevInitData::build($request);

        $this->assertNotNull($initData);

        $validator = new MaxWebAppInitDataValidator(config());
        $dto = $validator->validate((string) $initData);

        $this->assertSame(1002, $dto->maxUserId);
        $this->assertSame('Demo', $dto->firstName);
        $this->assertSame('VIP', $dto->lastName);
        $this->assertSame('demo_vip', $dto->username);
    }

    /** Собирает initData для настроенного адресного админа. */
    public function test_builds_init_data_for_configured_address_admin_user(): void
    {
        config(['max.local_dev_user_id' => 1003]);

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $initData = MaxLocalDevInitData::build($request);

        $this->assertNotNull($initData);

        $validator = new MaxWebAppInitDataValidator(config());
        $dto = $validator->validate((string) $initData);

        $this->assertSame(1003, $dto->maxUserId);
        $this->assertSame('Demo', $dto->firstName);
        $this->assertSame('Админ адреса', $dto->lastName);
        $this->assertSame('demo_address_admin', $dto->username);
    }

    /** Возвращает null для неизвестного local-dev user_id. */
    public function test_returns_null_for_unknown_local_dev_user_id(): void
    {
        config(['max.local_dev_user_id' => 9999]);

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertNull(MaxLocalDevInitData::build($request));
    }

    /** Отключён, когда флаг выключен. */
    public function test_is_disabled_when_flag_is_off(): void
    {
        config(['max.local_dev_init_data' => false]);

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertFalse(MaxLocalDevInitData::isEnabled($request));
        $this->assertNull(MaxLocalDevInitData::build($request));
    }

    /** Отключён на публичном туннель-хосте. */
    public function test_is_disabled_on_public_tunnel_host(): void
    {
        config([
            'max.webhook.url' => 'https://max-dev.example.test/api/webhooks/max',
        ]);

        $request = Request::create(
            'https://max-dev.example.test/max-app',
            'GET',
            server: [
                'HTTP_HOST' => 'max-dev.example.test',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $this->assertFalse(MaxLocalDevInitData::isEnabled($request));
        $this->assertNull(MaxLocalDevInitData::build($request));
    }

    /** Отключён вне локального окружения. */
    public function test_is_disabled_outside_local_environment(): void
    {
        config(['app.env' => 'production']);

        $request = Request::create(
            'http://127.0.0.1:8083/max-app',
            'GET',
            server: ['HTTP_HOST' => '127.0.0.1:8083'],
        );

        $this->assertFalse(MaxLocalDevInitData::isEnabled($request));
    }

    /** Подписчик совпадает с валидатором для фикстурного payload. */
    public function test_signer_matches_validator_for_fixture_payload(): void
    {
        $userPayload = json_encode([
            'id' => 67_890,
            'first_name' => 'Max',
            'last_name' => 'User',
            'username' => null,
            'language_code' => 'ru',
            'photo_url' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $initData = MaxWebAppInitDataSigner::sign(self::BOT_TOKEN, [
            'auth_date' => (string) time(),
            'query_id' => 'fixture-query',
            'user' => is_string($userPayload) ? $userPayload : '{}',
        ]);

        $validator = new MaxWebAppInitDataValidator(config());
        $dto = $validator->validate($initData);

        $this->assertSame(67_890, $dto->maxUserId);
    }
}
