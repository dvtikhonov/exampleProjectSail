<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Exceptions\Telegram\TelegramWebAppInitDataException;
use App\Services\Telegram\TelegramWebAppInitDataValidator;
use Tests\Support\TelegramInitDataFixtureBuilder;
use Tests\TestCase;

class TelegramWebAppInitDataValidatorTest extends TestCase
{
    private const BOT_TOKEN = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';

    private TelegramWebAppInitDataValidator $validator;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => self::BOT_TOKEN,
            'telegram.miniapp.auth_date_max_age_seconds' => 86_400,
        ]);

        $this->validator = $this->app->make(TelegramWebAppInitDataValidator::class);
    }

    /** Валидирует свежие initData. */
    public function test_validates_fresh_init_data(): void
    {
        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $dto = $this->validator->validate($fixture);

        $this->assertInstanceOf(TelegramWebAppInitDataDto::class, $dto);
        $this->assertSame(67_890, $dto->telegramUserId);
        $this->assertSame('Telegram', $dto->firstName);
        $this->assertSame('User', $dto->lastName);
        $this->assertSame('tg_user', $dto->username);
        $this->assertSame('ru', $dto->languageCode);
    }

    /** Signature входит в HMAC: подпись после добавления signature должна совпадать. */
    public function test_accepts_init_data_when_signature_is_included_in_hmac(): void
    {
        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'signature' => 'telegram-ed25519-placeholder',
            'chat_instance' => '8134722200314281151',
            'chat_type' => 'private',
        ]);

        $dto = $this->validator->validate($fixture);

        $this->assertSame(67_890, $dto->telegramUserId);
    }

    /** Подделка: signature, добавленный после подписи, ломает hash. */
    public function test_rejects_signature_appended_after_hash_was_computed(): void
    {
        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);
        $withSignature = $fixture.'&signature='.rawurlencode('not-part-of-hmac-check');

        $this->expectException(TelegramWebAppInitDataException::class);
        $this->expectExceptionMessage('hash mismatch');

        $this->validator->validate($withSignature);
    }

    /** Отклоняет подделанный hash. */
    public function test_rejects_tampered_hash(): void
    {
        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);
        $tampered = str_replace('Telegram', 'Hacker', $fixture);

        $this->expectException(TelegramWebAppInitDataException::class);
        $this->expectExceptionMessage('hash mismatch');

        $this->validator->validate($tampered);
    }

    /** Отклоняет истёкший auth_date. */
    public function test_rejects_expired_auth_date(): void
    {
        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN, [
            'auth_date' => (string) (time() - 90_000),
        ]);

        $this->expectException(TelegramWebAppInitDataException::class);
        $this->expectExceptionMessage('older than');

        $this->validator->validate($fixture);
    }

    /** Отклоняет, если токен бота не настроен. */
    public function test_rejects_when_bot_token_is_not_configured(): void
    {
        config(['telegram.bot_token' => '']);

        $fixture = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $this->expectException(TelegramWebAppInitDataException::class);
        $this->expectExceptionMessage('TELEGRAM_BOT_TOKEN');

        $this->validator->validate($fixture);
    }
}
