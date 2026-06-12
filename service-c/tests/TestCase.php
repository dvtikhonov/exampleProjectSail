<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    private static bool $pendingMigrationsApplied = false;

    protected function setUp(): void
    {
        // Таблицы создаёт prepare в test-services.sh (main-app, service-a/b/c migrations).
        RefreshDatabaseState::$migrated = true;

        $this->ensureTestingDatabaseReady();

        parent::setUp();

        config()->set('database.connections.mysql.database', 'sail_db_testing');
        config()->set('database.default', 'mysql');

        $this->resetMessMaxLogListeners();
    }

    protected function tearDown(): void
    {
        $this->resetMessMaxLogListeners();

        parent::tearDown();
    }

    private function ensureTestingDatabaseReady(): void
    {
        if (self::$pendingMigrationsApplied) {
            return;
        }

        if (! $this->app) {
            $this->refreshApplication();
        }

        config()->set('database.connections.mysql.database', 'sail_db_testing');
        config()->set('database.default', 'mysql');

        Artisan::call('migrate', ['--force' => true]);

        self::$pendingMigrationsApplied = true;
    }

    private function resetMessMaxLogListeners(): void
    {
        if (! isset($this->app)) {
            return;
        }

        $this->app['events']->forget(MessageLogged::class);
        $this->app->forgetInstance('log');
    }
}
