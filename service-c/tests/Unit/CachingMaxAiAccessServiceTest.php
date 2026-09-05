<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\DTO\Max\MaxUserIdentity;
use App\Services\Max\CachingMaxAiAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-тесты кэша статуса AI-доступа: hit, invalidate, expire, disabled.
 */
class CachingMaxAiAccessServiceTest extends TestCase
{
    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** Второй getStatus не зовёт inner — ответ из кэша. */
    public function test_get_status_hit_skips_inner_on_second_call(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:00:00');
        $expiresAt = '2026-09-05T10:30:00+00:00';
        $status = new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: 42,
            expiresAt: $expiresAt,
        );

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner
            ->expects($this->once())
            ->method('getStatus')
            ->with($now)
            ->willReturn($status);
        $inner->expects($this->never())->method('toggle');

        $service = $this->makeService($inner);

        $first = $service->getStatus($now);
        $second = $service->getStatus($now);

        $this->assertTrue($first->enabled);
        $this->assertSame(42, $first->activeMaxUserId);
        $this->assertSame($expiresAt, $first->expiresAt);
        $this->assertSame($first->enabled, $second->enabled);
        $this->assertSame($first->activeMaxUserId, $second->activeMaxUserId);
        $this->assertSame($first->expiresAt, $second->expiresAt);
        $this->assertTrue(Cache::has(CachingMaxAiAccessService::CACHE_KEY));
    }

    /** Disabled-статус тоже кэшируется: повторный getStatus без inner. */
    public function test_get_status_caches_disabled_payload(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:00:00');
        $status = new AiAccessStatusDto(
            enabled: false,
            activeMaxUserId: null,
            expiresAt: null,
        );

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($status);

        $service = $this->makeService($inner);

        $this->assertFalse($service->getStatus($now)->enabled);
        $this->assertFalse($service->getStatus($now)->enabled);
        $this->assertSame(
            [
                'enabled' => false,
                'active_max_user_id' => null,
                'expires_at' => null,
            ],
            $this->app->make(CacheStoreInterface::class)->get(CachingMaxAiAccessService::CACHE_KEY),
        );
    }

    /** После toggle кэш forget + put актуального статуса. */
    public function test_toggle_forgets_and_puts_fresh_status(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:00:00');
        $user = new MaxUserIdentity(7);
        $fresh = new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: 7,
            expiresAt: '2026-09-05T10:30:00+00:00',
        );

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner->expects($this->never())->method('getStatus');
        $inner
            ->expects($this->once())
            ->method('toggle')
            ->with($user, $now)
            ->willReturn($fresh);

        $cache = $this->createMock(CacheStoreInterface::class);
        $cache
            ->expects($this->once())
            ->method('forget')
            ->with(CachingMaxAiAccessService::CACHE_KEY)
            ->willReturn(true);
        $cache
            ->expects($this->once())
            ->method('put')
            ->with(
                CachingMaxAiAccessService::CACHE_KEY,
                [
                    'enabled' => true,
                    'active_max_user_id' => 7,
                    'expires_at' => '2026-09-05T10:30:00+00:00',
                ],
                1800,
            )
            ->willReturn(true);
        $cache->expects($this->never())->method('get');

        $service = new CachingMaxAiAccessService($inner, $cache, enabled: true);
        $result = $service->toggle($user, $now);

        $this->assertTrue($result->enabled);
        $this->assertSame(7, $result->activeMaxUserId);
    }

    /** Toggle при включённом кэше обновляет payload: последующий GET без stale. */
    public function test_toggle_invalidates_previous_cached_hit(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:00:00');
        $disabled = new AiAccessStatusDto(
            enabled: false,
            activeMaxUserId: null,
            expiresAt: null,
        );
        $enabled = new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: 11,
            expiresAt: '2026-09-05T10:30:00+00:00',
        );

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($disabled);
        $inner
            ->expects($this->once())
            ->method('toggle')
            ->willReturn($enabled);

        $service = $this->makeService($inner);

        $this->assertFalse($service->getStatus($now)->enabled);

        $toggled = $service->toggle(new MaxUserIdentity(11), $now);
        $this->assertTrue($toggled->enabled);

        $afterToggle = $service->getStatus($now);
        $this->assertTrue($afterToggle->enabled);
        $this->assertSame(11, $afterToggle->activeMaxUserId);
        $this->assertSame(
            [
                'enabled' => true,
                'active_max_user_id' => 11,
                'expires_at' => '2026-09-05T10:30:00+00:00',
            ],
            $this->app->make(CacheStoreInterface::class)->get(CachingMaxAiAccessService::CACHE_KEY),
        );
    }

    /** Просроченный expires_at в кэше → forget и miss во inner. */
    public function test_expired_cached_payload_triggers_miss(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:30:00');
        $cache = $this->app->make(CacheStoreInterface::class);
        $cache->put(
            CachingMaxAiAccessService::CACHE_KEY,
            [
                'enabled' => true,
                'active_max_user_id' => 99,
                'expires_at' => '2026-09-05T10:29:59+00:00',
            ],
            3600,
        );

        $fresh = new AiAccessStatusDto(
            enabled: false,
            activeMaxUserId: null,
            expiresAt: null,
        );

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner
            ->expects($this->once())
            ->method('getStatus')
            ->with($now)
            ->willReturn($fresh);

        $service = $this->makeService($inner);
        $result = $service->getStatus($now);

        $this->assertFalse($result->enabled);
        $this->assertNull($result->activeMaxUserId);
        $this->assertSame(
            [
                'enabled' => false,
                'active_max_user_id' => null,
                'expires_at' => null,
            ],
            $cache->get(CachingMaxAiAccessService::CACHE_KEY),
        );
    }

    /** При enabled=false декоратор всегда делегирует во inner и не пишет в кэш. */
    public function test_disabled_flag_always_calls_inner(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:00:00');
        $status = new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: 5,
            expiresAt: '2026-09-05T10:30:00+00:00',
        );
        $user = new MaxUserIdentity(5);

        $inner = $this->createMock(MaxAiAccessServiceInterface::class);
        $inner
            ->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn($status);
        $inner
            ->expects($this->once())
            ->method('toggle')
            ->with($user, $now)
            ->willReturn($status);

        $cache = $this->createMock(CacheStoreInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('put');
        $cache->expects($this->never())->method('forget');

        $service = new CachingMaxAiAccessService($inner, $cache, enabled: false);

        $this->assertTrue($service->getStatus($now)->enabled);
        $this->assertTrue($service->getStatus($now)->enabled);
        $this->assertTrue($service->toggle($user, $now)->enabled);
    }

    /**
     * @param  MaxAiAccessServiceInterface  $inner  Внутренний сервис.
     */
    private function makeService(MaxAiAccessServiceInterface $inner): CachingMaxAiAccessService
    {
        return new CachingMaxAiAccessService(
            $inner,
            $this->app->make(CacheStoreInterface::class),
            enabled: true,
        );
    }
}
