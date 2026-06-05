<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    private static bool $pendingMigrationsApplied = false;

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = true;

        parent::setUp();

        config()->set('database.connections.mysql.database', 'sail_db_testing');
        config()->set('database.default', 'mysql');

        $this->applyPendingTestingMigrations();
    }

    private function applyPendingTestingMigrations(): void
    {
        if (self::$pendingMigrationsApplied) {
            return;
        }

        Artisan::call('migrate', ['--force' => true]);

        self::$pendingMigrationsApplied = true;
    }
}
