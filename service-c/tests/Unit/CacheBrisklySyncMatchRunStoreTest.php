<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\CacheStoreInterface;
use App\Infrastructure\Briskly\CacheBrisklySyncMatchRunStore;
use App\Infrastructure\Laravel\LaravelCacheStore;
use Tests\TestCase;

/**
 * Cache generation handshake match.
 */
final class CacheBrisklySyncMatchRunStoreTest extends TestCase
{
    public function test_allocate_matches_then_forget(): void
    {
        $store = new CacheBrisklySyncMatchRunStore(
            $this->app->make(CacheStoreInterface::class),
        );

        $this->assertInstanceOf(LaravelCacheStore::class, $this->app->make(CacheStoreInterface::class));

        $generation = $store->allocate('sess-1', 60);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $generation,
        );
        $this->assertTrue($store->matches('sess-1', $generation));
        $this->assertFalse($store->matches('sess-1', '00000000-0000-4000-8000-000000000000'));

        $next = $store->allocate('sess-1', 60);
        $this->assertFalse($store->matches('sess-1', $generation));
        $this->assertTrue($store->matches('sess-1', $next));

        $store->forget('sess-1');
        $this->assertNull($store->get('sess-1'));
        $this->assertFalse($store->matches('sess-1', $next));
    }
}
