<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxRegistrationGreetingTrackerInterface;
use App\Contracts\Shared\CacheStoreInterface;

/**
 * Кэш-пометка об отправленном приветствии регистрации MAX.
 */
final class MaxRegistrationGreetingTracker implements MaxRegistrationGreetingTrackerInterface
{
    /**
     * v2: старый ключ без версии оставался true после сбоя доставки и блокировал кнопку.
     */
    private const string KEY_PREFIX = 'max.registration_greeting.sent.v2.';

    /**
     * Короткое окно на chat_id: второй webhook того же старта не шлёт второе «Привет».
     * Бессрочный ключ user_id это окно не заменяет — он уже блокировал доставку в другой диалог.
     */
    private const string CHAT_KEY_PREFIX = 'max.registration_greeting.chat.';

    private const int CHAT_CLAIM_TTL_SECONDS = 30;

    public function __construct(
        private readonly CacheStoreInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function wasSent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->cache->get($this->key($userId)) === true;
    }

    /**
     * {@inheritDoc}
     */
    public function markSent(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->cache->forever($this->key($userId), true);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->cache->forget($this->key($userId));
    }

    /**
     * {@inheritDoc}
     */
    public function claimChatDelivery(int $chatId): bool
    {
        if ($chatId <= 0) {
            return false;
        }

        return $this->cache->add($this->chatKey($chatId), true, self::CHAT_CLAIM_TTL_SECONDS);
    }

    /**
     * {@inheritDoc}
     */
    public function releaseChatDelivery(int $chatId): void
    {
        if ($chatId <= 0) {
            return;
        }

        $this->cache->forget($this->chatKey($chatId));
    }

    /**
     * Ключ кэша для user_id MAX.
     */
    private function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }

    /**
     * Ключ короткого занятия диалога bot_started.
     */
    private function chatKey(int $chatId): string
    {
        return self::CHAT_KEY_PREFIX.$chatId;
    }
}
