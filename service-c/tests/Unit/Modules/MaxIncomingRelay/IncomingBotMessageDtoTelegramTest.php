<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Enums\MessengerChannel;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use Tests\Support\TelegramIncomingRelayPayloadFactory;
use Tests\TestCase;

/**
 * Unit: разбор Telegram Update → IncomingBotMessageDto.
 */
final class IncomingBotMessageDtoTelegramTest extends TestCase
{
    /** Эталонный private text → внутренний max_user_id и messenger=telegram. */
    public function test_try_from_telegram_parses_private_text_message(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage();

        $dto = IncomingBotMessageDto::tryFromTelegram($payload);

        $this->assertNotNull($dto);
        $this->assertSame(MessengerChannel::Telegram, $dto->messenger);
        $this->assertSame(
            MessengerChannel::Telegram->toInternalMaxUserId(
                TelegramIncomingRelayPayloadFactory::DEFAULT_TELEGRAM_USER_ID,
            ),
            $dto->userId,
        );
        $this->assertSame(TelegramIncomingRelayPayloadFactory::DEFAULT_TELEGRAM_USER_ID, $dto->externalUserId());
        $this->assertSame(TelegramIncomingRelayPayloadFactory::DEFAULT_FIRST_NAME, $dto->firstName);
        $this->assertSame(TelegramIncomingRelayPayloadFactory::DEFAULT_LAST_NAME, $dto->lastName);
        $this->assertSame(TelegramIncomingRelayPayloadFactory::DEFAULT_TEXT, $dto->text);
        $this->assertSame(
            TelegramIncomingRelayPayloadFactory::DEFAULT_DATE_SECONDS * 1000,
            $dto->timestampMs,
        );
        $this->assertSame(TelegramIncomingRelayPayloadFactory::DEFAULT_TELEGRAM_USER_ID, $dto->chatId);
    }

    /** caption используется, если нет text. */
    public function test_try_from_telegram_reads_caption_when_text_missing(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage([
            'use_caption' => true,
            'caption' => 'из caption',
        ]);

        $dto = IncomingBotMessageDto::tryFromTelegram($payload);

        $this->assertNotNull($dto);
        $this->assertSame('из caption', $dto->text);
    }

    /** is_bot: true → null. */
    public function test_try_from_telegram_returns_null_when_from_is_bot(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage(['is_bot' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFromTelegram($payload));
    }

    /** Не-private chat → null. */
    public function test_try_from_telegram_returns_null_for_group_chat(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage(['chat_type' => 'group']);

        $this->assertNull(IncomingBotMessageDto::tryFromTelegram($payload));
    }

    /** Нет from → null. */
    public function test_try_from_telegram_returns_null_when_from_missing(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage(['omit_from' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFromTelegram($payload));
    }

    /** Нет message → null. */
    public function test_try_from_telegram_returns_null_when_message_missing(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage(['omit_message' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFromTelegram($payload));
    }

    /** username и language_code читаются из from. */
    public function test_try_from_telegram_parses_username_and_language_code(): void
    {
        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage([
            'username' => 'demo_tg',
            'language_code' => 'ru',
        ]);

        $dto = IncomingBotMessageDto::tryFromTelegram($payload);

        $this->assertNotNull($dto);
        $this->assertSame('demo_tg', $dto->username);
        $this->assertSame('ru', $dto->languageCode);
    }

    /** /start и варианты с @bot / payload распознаются как start-команда. */
    public function test_is_start_command_detects_variants(): void
    {
        $start = IncomingBotMessageDto::tryFromTelegram(
            TelegramIncomingRelayPayloadFactory::privateTextMessage(['text' => '/start']),
        );
        $withBot = IncomingBotMessageDto::tryFromTelegram(
            TelegramIncomingRelayPayloadFactory::privateTextMessage(['text' => '/start@obedov_bot']),
        );
        $withPayload = IncomingBotMessageDto::tryFromTelegram(
            TelegramIncomingRelayPayloadFactory::privateTextMessage(['text' => '/start abc']),
        );
        $other = IncomingBotMessageDto::tryFromTelegram(
            TelegramIncomingRelayPayloadFactory::privateTextMessage(['text' => 'start']),
        );

        $this->assertTrue($start?->isStartCommand());
        $this->assertTrue($withBot?->isStartCommand());
        $this->assertTrue($withPayload?->isStartCommand());
        $this->assertFalse($other?->isStartCommand());
    }
}
