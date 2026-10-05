<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\Infrastructure\Briskly\HttpBrisklySyncVpsCatalogGateway;
use App\Services\Food\BrisklySync\LocalBrisklySyncVpsCatalog;
use Tests\TestCase;

/**
 * DI: local при host APP_URL = mini-app; Http — при source_remote=true.
 */
final class BrisklySyncVpsCatalogPortBindingTest extends TestCase
{
    /** phpunit.xml: APP_URL host = MAX_MINI_APP_URL host → local path. */
    public function test_default_phpunit_config_keeps_local_source(): void
    {
        $this->assertSame('http://localhost', config('briskly_sync.source_base_url'));
        $this->assertFalse((bool) config('briskly_sync.source_remote'));
        $this->assertInstanceOf(
            LocalBrisklySyncVpsCatalog::class,
            $this->app->make(BrisklySyncVpsCatalogPortInterface::class),
        );
    }

    /** При source_remote биндится Http-gateway. */
    public function test_source_remote_binds_http_gateway(): void
    {
        config([
            'briskly_sync.source_remote' => true,
            'briskly_sync.source_base_url' => 'https://94-228-117-27.sslip.io',
            'phototext.agent_token' => 'binding-token',
            'briskly_sync.source_timeout_seconds' => 30,
        ]);

        $this->app->forgetInstance(BrisklySyncVpsCatalogPortInterface::class);

        $this->assertInstanceOf(
            HttpBrisklySyncVpsCatalogGateway::class,
            $this->app->make(BrisklySyncVpsCatalogPortInterface::class),
        );
    }
}
