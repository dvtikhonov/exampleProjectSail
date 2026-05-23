<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = true;

        parent::setUp();

        config()->set('database.connections.mysql.database', 'sail_db_testing');
        config()->set('database.default', 'mysql');

        $this->ensurePassportPersonalAccessClientExists();
    }

    private function ensurePassportPersonalAccessClientExists(): void
    {
        if (DB::table('oauth_clients')->whereJsonContains('grant_types', 'personal_access')->exists()) {
            return;
        }

        DB::table('oauth_clients')->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Test Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => json_encode([]),
            'grant_types' => json_encode(['personal_access']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
