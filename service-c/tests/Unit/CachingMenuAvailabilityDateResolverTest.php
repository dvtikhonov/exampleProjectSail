<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use App\Services\Food\Menu\CachingMenuAvailabilityDateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-тесты кэша даты «Блюда на» на календарный день.
 */
class CachingMenuAvailabilityDateResolverTest extends TestCase
{
    /** Повторный resolve() в тот же день не дергает внутренний resolver. */
    public function test_resolve_uses_day_cache_and_skips_inner_on_second_call(): void
    {
        Cache::flush();

        $inner = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $inner
            ->expects($this->once())
            ->method('resolve')
            ->willReturn(new MenuAvailabilityDateResultDto(date: '2026-08-02', error: null));
        $inner->expects($this->never())->method('resolveForCurrentWeekday');

        $resolver = new CachingMenuAvailabilityDateResolver(
            $inner,
            $this->app->make(CacheStoreInterface::class),
        );

        $now = CarbonImmutable::parse('2026-07-31', 'Europe/Moscow');
        $first = $resolver->resolve($now);
        $second = $resolver->resolve($now);

        $this->assertSame('2026-08-02', $first->date);
        $this->assertSame($first->date, $second->date);
        $this->assertSame($first->error, $second->error);
    }

    /** resolveForCurrentWeekday() всегда делегирует без кэша. */
    public function test_resolve_for_current_weekday_always_delegates(): void
    {
        $inner = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $inner->expects($this->never())->method('resolve');
        $inner
            ->expects($this->exactly(2))
            ->method('resolveForCurrentWeekday')
            ->willReturn(new MenuAvailabilityDateResultDto(date: '2026-08-01', error: null));

        $resolver = new CachingMenuAvailabilityDateResolver(
            $inner,
            $this->app->make(CacheStoreInterface::class),
        );

        $now = CarbonImmutable::parse('2026-07-31', 'Europe/Moscow');
        $this->assertSame('2026-08-01', $resolver->resolveForCurrentWeekday($now)->date);
        $this->assertSame('2026-08-01', $resolver->resolveForCurrentWeekday($now)->date);
    }

    /** Результат «нет данных» не пишется в day-cache и не блокирует следующий resolve. */
    public function test_resolve_does_not_cache_no_data_result(): void
    {
        Cache::flush();

        $now = CarbonImmutable::now('Europe/Moscow')->startOfDay();
        $key = 'food.menu_availability_date.resolve.'.$now->format('Y-m-d');

        $inner = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $inner
            ->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnOnConsecutiveCalls(
                new MenuAvailabilityDateResultDto(date: null, error: 'нет данных'),
                new MenuAvailabilityDateResultDto(date: '2026-08-01', error: null),
            );

        $cache = $this->app->make(CacheStoreInterface::class);
        $resolver = new CachingMenuAvailabilityDateResolver($inner, $cache);

        $first = $resolver->resolve($now);
        $this->assertNull($first->date);
        $this->assertSame('нет данных', $first->error);
        $this->assertNull($cache->get($key));

        // Новый экземпляр — без requestMemo, как при следующем HTTP-запросе.
        $resolver2 = new CachingMenuAvailabilityDateResolver($inner, $cache);
        $second = $resolver2->resolve($now);
        $this->assertSame('2026-08-01', $second->date);
        $this->assertNull($second->error);
        $this->assertSame(
            ['date' => '2026-08-01', 'error' => null],
            $cache->get($key),
        );
    }

    /** Уже закэшированный negative payload игнорируется и пересчитывается. */
    public function test_resolve_ignores_stale_cached_no_data_payload(): void
    {
        Cache::flush();

        $now = CarbonImmutable::now('Europe/Moscow')->startOfDay();
        $key = 'food.menu_availability_date.resolve.'.$now->format('Y-m-d');
        $cache = $this->app->make(CacheStoreInterface::class);
        $ttlSeconds = max(1, $now->endOfDay()->getTimestamp() - time());
        $cache->put($key, ['date' => null, 'error' => 'нет данных'], $ttlSeconds);

        $inner = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $inner
            ->expects($this->once())
            ->method('resolve')
            ->willReturn(new MenuAvailabilityDateResultDto(date: '2026-08-01', error: null));

        $resolver = new CachingMenuAvailabilityDateResolver($inner, $cache);
        $result = $resolver->resolve($now);

        $this->assertSame('2026-08-01', $result->date);
        $this->assertSame(
            ['date' => '2026-08-01', 'error' => null],
            $cache->get($key),
        );
    }
}
