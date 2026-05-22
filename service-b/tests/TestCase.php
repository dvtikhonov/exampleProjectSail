<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql.database', 'sail_db_service_b_testing');
        config()->set('database.default', 'mysql');
    }
}
