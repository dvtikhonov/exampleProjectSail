<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Кэш успешного resolve() на календарный день (MSK) и в рамках экземпляра.
 *
 * Отрицательные результаты («нет данных» / date=null) не кэшируются.
 * resolveForCurrentWeekday() не кэшируется — используется cron/sync без lookback.
 */
class CachingMenuAvailabilityDateResolver implements MenuAvailabilityDateResolverInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    private const string CACHE_KEY_PREFIX = 'food.menu_availability_date.resolve.';

    /**
     * @var array<string, MenuAvailabilityDateResultDto>
     */
    private array $requestMemo = [];

    public function __construct(
        private readonly MenuAvailabilityDateResolverInterface $resolver,
        private readonly CacheStoreInterface $cache,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(?DateTimeInterface $now = null): MenuAvailabilityDateResultDto
    {
        $day = $this->toMoscowStartOfDay($now);
        $key = self::CACHE_KEY_PREFIX.$day->format('Y-m-d');

        if (isset($this->requestMemo[$key])) {
            return $this->requestMemo[$key];
        }

        $cached = $this->cache->get($key);
        $result = $this->dtoFromCachePayload($cached);

        if ($result === null) {
            if ($cached !== null) {
                $this->cache->forget($key);
            }

            $computed = $this->resolver->resolve($now ?? $day);

            // Не кэшируем «нет данных»: offsets могут появиться в течение дня
            // (админка / тесты / mid-day updates), иначе stale null залипает до конца суток.
            if ($computed->date !== null && $computed->error === null) {
                $endOfDay = $day->setTime(23, 59, 59);
                $ttlSeconds = max(1, $endOfDay->getTimestamp() - time());
                $this->cache->put(
                    $key,
                    [
                        'date' => $computed->date,
                        'error' => $computed->error,
                    ],
                    $ttlSeconds,
                );
            }

            $result = $computed;
        }

        $this->requestMemo[$key] = $result;

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveForCurrentWeekday(?DateTimeInterface $now = null): MenuAvailabilityDateResultDto
    {
        return $this->resolver->resolveForCurrentWeekday($now);
    }

    /**
     * Нормализует «сейчас» к началу календарного дня Europe/Moscow.
     */
    private function toMoscowStartOfDay(?DateTimeInterface $now): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromInterface($now ?? $this->clock->now());

        return $instant
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0);
    }

    /**
     * Восстанавливает DTO из кэша (только array-payload).
     */
    private function dtoFromCachePayload(mixed $cached): ?MenuAvailabilityDateResultDto
    {
        if (! is_array($cached)) {
            return null;
        }

        if (! array_key_exists('date', $cached) || ! array_key_exists('error', $cached)) {
            return null;
        }

        $date = $cached['date'];
        $error = $cached['error'];

        if ($date !== null && ! is_string($date)) {
            return null;
        }

        if ($error !== null && ! is_string($error)) {
            return null;
        }

        // Устаревшие/ошибочные negative-payload не используем как hit.
        if ($date === null || $error !== null) {
            return null;
        }

        return new MenuAvailabilityDateResultDto(
            date: $date,
            error: $error,
        );
    }
}
