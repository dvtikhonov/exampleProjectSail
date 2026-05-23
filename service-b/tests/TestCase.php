<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = true;

        parent::setUp();

        config()->set('database.connections.mysql.database', 'sail_db_testing');
        config()->set('database.default', 'mysql');
    }
}
