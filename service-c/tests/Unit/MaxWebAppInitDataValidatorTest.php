<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Max\MaxWebAppInitDataDto;
use App\Exceptions\Max\MaxWebAppInitDataException;
use App\Services\Max\MaxWebAppInitDataValidator;
use Tests\Support\MaxInitDataFixtureBuilder;
use Tests\TestCase;

class MaxWebAppInitDataValidatorTest extends TestCase
{
    private const BOT_TOKEN = 'test-bot-token-for-init-data-validation';

    private MaxWebAppInitDataValidator $validator;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::BOT_TOKEN,
            'max.miniapp.auth_date_max_age_seconds' => 86_400,
        ]);

        $this->validator = new MaxWebAppInitDataValidator(config());
    }

    /** Валидирует фиксированную фикстуру, когда проверка auth_date отключена. */
    public function test_validates_fixed_fixture_when_auth_date_check_is_disabled_for_fixture(): void
    {
        config(['max.miniapp.auth_date_max_age_seconds' => PHP_INT_MAX]);

        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'auth_date' => '1771409719',
        ]);

        $dto = $this->validator->validate($fixture);

        $this->assertInstanceOf(MaxWebAppInitDataDto::class, $dto);
        $this->assertSame(67_890, $dto->maxUserId);
        $this->assertSame('Max', $dto->firstName);
        $this->assertSame('User', $dto->lastName);
        $this->assertNull($dto->username);
        $this->assertSame('ru', $dto->languageCode);
        $this->assertNull($dto->photoUrl);
        $this->assertSame(1_771_409_719, $dto->authDate);
        $this->assertSame('4c0ab423-342b-4e45-aea4-2747dbc500cd', $dto->queryId);
    }

    /** Валидирует свежие initData. */
    public function test_validates_fresh_init_data(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $dto = $this->validator->validate($fixture);

        $this->assertSame(67_890, $dto->maxUserId);
        $this->assertSame('Max', $dto->firstName);
    }

    /** Отклоняет подделанный hash. */
    public function test_rejects_tampered_hash(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);
        $tampered = str_replace('Max', 'Hacker', $fixture);

        $this->expectException(MaxWebAppInitDataException::class);
        $this->expectExceptionMessage('hash mismatch');

        $this->validator->validate($tampered);
    }

    /** Отклоняет истёкший auth_date. */
    public function test_rejects_expired_auth_date(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'auth_date' => (string) (time() - 90_000),
        ]);

        $this->expectException(MaxWebAppInitDataException::class);
        $this->expectExceptionMessage('older than');

        $this->validator->validate($fixture);
    }

    /** Отклоняет дублирующиеся ключи параметров. */
    public function test_rejects_duplicate_parameter_keys(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN).'&auth_date=1771409719';

        $this->expectException(MaxWebAppInitDataException::class);
        $this->expectExceptionMessage('duplicated');

        $this->validator->validate($fixture);
    }

    /** Отклоняет отсутствующий user payload. */
    public function test_rejects_missing_user_payload(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'user' => '',
        ]);

        $this->expectException(MaxWebAppInitDataException::class);
        $this->expectExceptionMessage('user is missing');

        $this->validator->validate($fixture);
    }

    /** Отклоняет, если токен бота не настроен. */
    public function test_rejects_when_bot_token_is_not_configured(): void
    {
        config(['max.bot_access_token' => '']);

        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $this->expectException(MaxWebAppInitDataException::class);
        $this->expectExceptionMessage('MAX_BOT_ACCESS_TOKEN');

        $this->validator->validate($fixture);
    }

    /** Парсит опциональный параметр chat. */
    public function test_parses_optional_chat_parameter(): void
    {
        $fixture = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'chat' => json_encode([
                'id' => 12_345,
                'type' => 'DIALOG',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => '192.168.0.1',
        ]);

        $dto = $this->validator->validate($fixture);

        $this->assertSame(['id' => 12_345, 'type' => 'DIALOG'], $dto->chat);
        $this->assertSame('192.168.0.1', $dto->ip);
    }
}
