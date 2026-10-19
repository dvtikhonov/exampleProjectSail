<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Enums\MessengerChannel;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Идентичность max_users: messenger + external_user_id и offset для Telegram.
 */
class MaxUserMessengerIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** Одинаковый external id у MAX и Telegram — две строки и две независимые идентичности. */
    public function test_same_external_id_creates_distinct_rows_for_max_and_telegram(): void
    {
        $externalId = 777;

        $maxUser = MaxUser::query()->create([
            'max_user_id' => MessengerChannel::Max->toInternalMaxUserId($externalId),
            'messenger' => MessengerChannel::Max,
            'external_user_id' => $externalId,
            'first_name' => 'MaxUser',
        ]);

        $telegramUser = MaxUser::query()->create([
            'max_user_id' => MessengerChannel::Telegram->toInternalMaxUserId($externalId),
            'messenger' => MessengerChannel::Telegram,
            'external_user_id' => $externalId,
            'first_name' => 'TgUser',
        ]);

        $this->assertSame(777, $maxUser->max_user_id);
        $this->assertSame(1_000_000_000_000_777, $telegramUser->max_user_id);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 777,
            'messenger' => 'max',
            'external_user_id' => 777,
        ]);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 1_000_000_000_000_777,
            'messenger' => 'telegram',
            'external_user_id' => 777,
        ]);

        /** @var MaxUserIdentityRepositoryInterface $identity */
        $identity = $this->app->make(MaxUserIdentityRepositoryInterface::class);

        $foundMax = $identity->findByMessengerAndExternalUserId(MessengerChannel::Max, $externalId);
        $foundTelegram = $identity->findByMessengerAndExternalUserId(MessengerChannel::Telegram, $externalId);

        $this->assertNotNull($foundMax);
        $this->assertNotNull($foundTelegram);
        $this->assertSame(777, $foundMax->maxUserId);
        $this->assertSame(1_000_000_000_000_777, $foundTelegram->maxUserId);
        $this->assertSame(MessengerChannel::Max, $foundMax->messenger);
        $this->assertSame(MessengerChannel::Telegram, $foundTelegram->messenger);

        $resolvedTelegram = $identity->resolveMessengerIdentity($telegramUser->max_user_id);
        $this->assertNotNull($resolvedTelegram);
        $this->assertSame(MessengerChannel::Telegram, $resolvedTelegram->messenger);
        $this->assertSame($externalId, $resolvedTelegram->externalUserId);
    }

    /** Creating без явных messenger/external заполняет их для канала MAX. */
    public function test_creating_max_user_defaults_messenger_and_external_user_id(): void
    {
        $user = MaxUser::query()->create([
            'max_user_id' => 42_001,
            'first_name' => 'Defaulted',
        ]);

        $this->assertSame(MessengerChannel::Max, $user->messenger);
        $this->assertSame(42_001, $user->external_user_id);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 42_001,
            'messenger' => 'max',
            'external_user_id' => 42_001,
        ]);
    }
}
