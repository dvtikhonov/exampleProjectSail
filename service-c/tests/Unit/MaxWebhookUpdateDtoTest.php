<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Max\MaxWebhookUpdateDto;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaxWebhookUpdateDtoTest extends TestCase
{
    /** Не-массив и отсутствие update_type дают null. */
    #[DataProvider('invalidRawProvider')]
    public function test_try_from_returns_null_for_invalid_raw(mixed $raw): void
    {
        $this->assertNull(MaxWebhookUpdateDto::tryFrom($raw));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidRawProvider(): array
    {
        return [
            'null' => [null],
            'string' => ['garbage'],
            'empty array' => [[]],
            'missing update_type' => [['timestamp' => 1]],
            'empty update_type' => [['update_type' => '']],
            'non-string update_type' => [['update_type' => 123]],
        ];
    }

    /** Валидный update_type принимается; неизвестные поля сохраняются в payload. */
    public function test_try_from_accepts_valid_update_and_preserves_payload(): void
    {
        $raw = [
            'update_type' => 'message_callback',
            'timestamp' => 1739184000000,
            'callback' => ['callback_id' => 'cb-1'],
            'extra_unknown' => true,
        ];

        $dto = MaxWebhookUpdateDto::tryFrom($raw);

        $this->assertNotNull($dto);
        $this->assertSame('message_callback', $dto->updateType);
        $this->assertSame(1739184000000, $dto->timestamp);
        $this->assertSame($raw, $dto->payload);
        $this->assertTrue($dto->payload['extra_unknown']);
    }

    /** Нечисловой timestamp игнорируется, DTO всё равно принимается. */
    public function test_try_from_ignores_non_numeric_timestamp(): void
    {
        $dto = MaxWebhookUpdateDto::tryFrom([
            'update_type' => 'bot_started',
            'timestamp' => 'not-a-number',
        ]);

        $this->assertNotNull($dto);
        $this->assertSame('bot_started', $dto->updateType);
        $this->assertNull($dto->timestamp);
    }

    /** Числовой timestamp в строке приводится к int. */
    public function test_try_from_casts_numeric_string_timestamp(): void
    {
        $dto = MaxWebhookUpdateDto::tryFrom([
            'update_type' => 'bot_started',
            'timestamp' => '1739184000000',
        ]);

        $this->assertNotNull($dto);
        $this->assertSame(1739184000000, $dto->timestamp);
    }
}
