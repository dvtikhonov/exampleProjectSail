<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MessengerChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Формула внутреннего max_user_id для каналов MAX / Telegram.
 */
class MessengerChannelTest extends TestCase
{
    /** Offset зафиксирован в коде как 1e15. */
    public function test_telegram_offset_constant(): void
    {
        $this->assertSame(1_000_000_000_000_000, MessengerChannel::TELEGRAM_MAX_USER_ID_OFFSET);
    }

    /**
     * @return array<string, array{0: MessengerChannel, 1: int, 2: int}>
     */
    public static function internalIdProvider(): array
    {
        return [
            'max same id' => [MessengerChannel::Max, 777, 777],
            'telegram offset + id' => [MessengerChannel::Telegram, 777, 1_000_000_000_000_777],
            'telegram large id' => [MessengerChannel::Telegram, 5_432_109_876, 1_000_005_432_109_876],
        ];
    }

    #[DataProvider('internalIdProvider')]
    /** toInternalMaxUserId / externalUserIdFromInternal симметричны. */
    public function test_internal_and_external_id_round_trip(
        MessengerChannel $channel,
        int $externalUserId,
        int $expectedInternalId,
    ): void {
        $this->assertSame($expectedInternalId, $channel->toInternalMaxUserId($externalUserId));
        $this->assertSame($externalUserId, $channel->externalUserIdFromInternal($expectedInternalId));
    }

    /** Одинаковый внешний id у MAX и Telegram даёт разные внутренние PK. */
    public function test_same_external_id_yields_distinct_internal_keys(): void
    {
        $externalId = 777;

        $maxInternal = MessengerChannel::Max->toInternalMaxUserId($externalId);
        $telegramInternal = MessengerChannel::Telegram->toInternalMaxUserId($externalId);

        $this->assertNotSame($maxInternal, $telegramInternal);
        $this->assertSame($externalId, $maxInternal);
        $this->assertSame(
            MessengerChannel::TELEGRAM_MAX_USER_ID_OFFSET + $externalId,
            $telegramInternal,
        );
    }
}
