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
     * Ключ кэша для user_id MAX.
     */
    private function key(int $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }
}
