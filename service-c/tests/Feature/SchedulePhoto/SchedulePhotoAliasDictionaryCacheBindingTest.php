<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryCacheInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lookup и сброс кеша словаря обязаны быть одним объектом.
 */
final class SchedulePhotoAliasDictionaryCacheBindingTest extends TestCase
{
    #[Test]
    public function lookup_and_cache_ports_resolve_the_same_singleton(): void
    {
        $lookup = $this->app->make(SchedulePhotoAliasDictionaryInterface::class);
        $cache = $this->app->make(SchedulePhotoAliasDictionaryCacheInterface::class);

        $this->assertSame($lookup, $cache);
    }
}
