<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use Tests\Support\MaxIncomingRelayPayloadFactory;
use Tests\TestCase;

/**
 * Unit: разбор реального webhook Update message_created → IncomingBotMessageDto.
 */
final class IncomingBotMessageDtoTest extends TestCase
{
    /** Эталонный MAX JSON → поля DTO по путям message.sender / body / recipient. */
    public function test_try_from_parses_canonical_max_text_dialog_payload(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog();

        $dto = IncomingBotMessageDto::tryFrom($payload);

        $this->assertNotNull($dto);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_USER_ID, $dto->userId);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_FIRST_NAME, $dto->firstName);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_LAST_NAME, $dto->lastName);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_TEXT, $dto->text);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_TIMESTAMP_MS, $dto->timestampMs);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_CHAT_ID, $dto->chatId);
    }

    /** Текст берётся из message.body.text, не из message.text. */
    public function test_try_from_reads_text_from_message_body_not_message_root(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['text' => 'из body']);
        $payload['message']['text'] = 'неверный путь';

        $dto = IncomingBotMessageDto::tryFrom($payload);

        $this->assertNotNull($dto);
        $this->assertSame('из body', $dto->text);
    }

    /** sender.is_bot: true → null (пересылки нет). */
    public function test_try_from_returns_null_when_sender_is_bot(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['is_bot' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFrom($payload));
    }

    /** Нет message.sender (пост канала) → null. */
    public function test_try_from_returns_null_when_sender_missing(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['omit_sender' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFrom($payload));
    }

    /** Нет message → null. */
    public function test_try_from_returns_null_when_message_missing(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['omit_message' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFrom($payload));
    }

    /** Нет body → null. */
    public function test_try_from_returns_null_when_body_missing(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['omit_body' => true]);

        $this->assertNull(IncomingBotMessageDto::tryFrom($payload));
    }

    /** user_id <= 0 → null. */
    public function test_try_from_returns_null_when_user_id_not_positive(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['user_id' => 0]);

        $this->assertNull(IncomingBotMessageDto::tryFrom($payload));
    }

    /** Пустой text в body допустим (пустая строка). */
    public function test_try_from_allows_empty_text(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog(['text' => '']);

        $dto = IncomingBotMessageDto::tryFrom($payload);

        $this->assertNotNull($dto);
        $this->assertSame('', $dto->text);
    }

    /** Без first_name/last_name — пустые строки в DTO. */
    public function test_try_from_allows_missing_names(): void
    {
        $payload = MaxIncomingRelayPayloadFactory::textDialog([
            'first_name' => null,
            'last_name' => null,
        ]);
        unset($payload['message']['sender']['first_name'], $payload['message']['sender']['last_name']);

        $dto = IncomingBotMessageDto::tryFrom($payload);

        $this->assertNotNull($dto);
        $this->assertSame('', $dto->firstName);
        $this->assertSame('', $dto->lastName);
    }
}
