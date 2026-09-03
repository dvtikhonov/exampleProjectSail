<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\CacheStoreInterface;
use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use Psr\Log\LoggerInterface;
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
        $cache = $this->createMock(CacheStoreInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(MenuCatalogCacheInvalidator::VERSION_CACHE_KEY, 1)
            ->willReturn(2);
        $cache->expects($this->once())
            ->method('forever')
            ->with(MenuCatalogCacheInvalidator::VERSION_CACHE_KEY, 3);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $invalidator = new MenuCatalogCacheInvalidator($cache, $logger);
        $invalidator->invalidateAll();
    }

    /** Permission denied / любая ошибка store — только warning в лог, без исключения. */
    public function test_invalidate_all_swallows_cache_write_failure(): void
    {
        $cache = $this->createMock(CacheStoreInterface::class);
        $cache->method('get')->willReturn(1);
        $cache->method('forever')->willThrowException(
            new RuntimeException('Failed to open stream: Permission denied'),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Menu catalog cache invalidation failed.',
                $this->callback(function (array $context): bool {
                    return ($context['message'] ?? '') === 'Failed to open stream: Permission denied';
                }),
            );

        $invalidator = new MenuCatalogCacheInvalidator($cache, $logger);
        $invalidator->invalidateAll();
    }
}
