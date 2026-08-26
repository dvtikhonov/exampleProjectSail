<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Инвалидация кэша каталога не должна ронять админ-сохранение при сбое store.
 */
class MenuCatalogCacheInvalidatorTest extends TestCase
{
    /** При рабочем store версия увеличивается. */
    public function test_invalidate_all_bumps_version(): void
    {
        $cache = $this->createMock(CacheRepository::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(MenuCatalogCacheInvalidator::VERSION_CACHE_KEY, 1)
            ->willReturn(2);
        $cache->expects($this->once())
            ->method('forever')
            ->with(MenuCatalogCacheInvalidator::VERSION_CACHE_KEY, 3);

        $invalidator = new MenuCatalogCacheInvalidator($cache);
        $invalidator->invalidateAll();
    }

    /** Permission denied / любая ошибка store — только warning в лог, без исключения. */
    public function test_invalidate_all_swallows_cache_write_failure(): void
    {
        Log::spy();

        $cache = $this->createMock(CacheRepository::class);
        $cache->method('get')->willReturn(1);
        $cache->method('forever')->willThrowException(
            new RuntimeException('Failed to open stream: Permission denied'),
        );

        $invalidator = new MenuCatalogCacheInvalidator($cache);
        $invalidator->invalidateAll();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Menu catalog cache invalidation failed.'
                    && ($context['message'] ?? '') === 'Failed to open stream: Permission denied';
            });
    }
}
