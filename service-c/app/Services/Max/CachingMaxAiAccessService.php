<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\DTO\Max\MaxUserIdentity;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Кэш статуса AI-доступа (один ключ: get / put / forget, без version-bump).
 *
 * Источник истины — БД через «сырой» {@see MaxAiAccessService}.
 * На cache hit cleanup просрочки в БД не вызывается.
 */
class CachingMaxAiAccessService implements MaxAiAccessServiceInterface
{
    public const string CACHE_KEY = 'max.ai_access.status';

    private const int DISABLED_TTL_SECONDS = 60;

    public function __construct(
        private readonly MaxAiAccessServiceInterface $inner,
        private readonly CacheStoreInterface $cache,
        private readonly bool $enabled = true,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getStatus(DateTimeInterface $now): AiAccessStatusDto
    {
        if (! $this->enabled) {
            return $this->inner->getStatus($now);
        }
        $cached = $this->cache->get(self::CACHE_KEY);
        $status = $this->statusFromCachePayload($cached, $now);

        if ($status !== null) {
            return $status;
        }

        if ($cached !== null) {
            $this->cache->forget(self::CACHE_KEY);
        }

        $status = $this->inner->getStatus($now);
        $this->putStatus($status, $now);

        return $status;
    }

    /**
     * {@inheritDoc}
     */
    public function toggle(MaxUserIdentity $currentUser, DateTimeInterface $now): AiAccessStatusDto
    {
        $status = $this->inner->toggle($currentUser, $now);

        if (! $this->enabled) {
            return $status;
        }

        $this->cache->forget(self::CACHE_KEY);
        $this->putStatus($status, $now);

        return $status;
    }

    /**
     * Кладёт статус в кэш с TTL: до expires_at (если включён) или safety-net 60 с.
     */
    private function putStatus(AiAccessStatusDto $status, DateTimeInterface $now): void
    {
        $this->cache->put(
            self::CACHE_KEY,
            [
                'enabled' => $status->enabled,
                'active_max_user_id' => $status->activeMaxUserId,
                'expires_at' => $status->expiresAt,
            ],
            $this->ttlSeconds($status, $now),
        );
    }

    /**
     * TTL кэша: при включённом доступе — секунды до expires_at (минимум 1);
     * при выключенном — 60 с.
     */
    private function ttlSeconds(AiAccessStatusDto $status, DateTimeInterface $now): int
    {
        if (! $status->enabled || $status->expiresAt === null) {
            return self::DISABLED_TTL_SECONDS;
        }

        $expiresAt = CarbonImmutable::parse($status->expiresAt);
        $seconds = $expiresAt->getTimestamp() - CarbonImmutable::instance($now)->getTimestamp();

        return max(1, $seconds);
    }

    /**
     * Восстанавливает DTO из кэша или null при miss / просрочке / битом payload.
     */
    private function statusFromCachePayload(mixed $cached, DateTimeInterface $now): ?AiAccessStatusDto
    {
        if (! is_array($cached)) {
            return null;
        }

        if (! array_key_exists('enabled', $cached)
            || ! array_key_exists('active_max_user_id', $cached)
            || ! array_key_exists('expires_at', $cached)
        ) {
            return null;
        }

        if (! is_bool($cached['enabled'])) {
            return null;
        }

        $activeMaxUserId = $cached['active_max_user_id'];
        if ($activeMaxUserId !== null && ! is_int($activeMaxUserId)) {
            if (is_string($activeMaxUserId) && ctype_digit($activeMaxUserId)) {
                $activeMaxUserId = (int) $activeMaxUserId;
            } else {
                return null;
            }
        }

        $expiresAt = $cached['expires_at'];
        if ($expiresAt !== null && ! is_string($expiresAt)) {
            return null;
        }

        if ($cached['enabled'] === false) {
            return new AiAccessStatusDto(
                enabled: false,
                activeMaxUserId: null,
                expiresAt: null,
            );
        }

        if ($expiresAt === null) {
            return null;
        }

        $expiresAtMoment = CarbonImmutable::parse($expiresAt);
        if ($expiresAtMoment->getTimestamp() <= CarbonImmutable::instance($now)->getTimestamp()) {
            return null;
        }

        return new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: $activeMaxUserId,
            expiresAt: $expiresAt,
        );
    }
}
