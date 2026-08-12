<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

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
        private readonly CacheRepository $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto
    {
        $day = ($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->timezone(self::TIMEZONE)
            ->startOfDay();
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
                $this->cache->put(
                    $key,
                    [
                        'date' => $computed->date,
                        'error' => $computed->error,
                    ],
                    $day->endOfDay(),
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
    public function resolveForCurrentWeekday(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto
    {
        return $this->resolver->resolveForCurrentWeekday($now);
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
