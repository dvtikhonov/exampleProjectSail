<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->ensurePassportPersonalAccessClientExists();
    }

    private function ensurePassportPersonalAccessClientExists(): void
    {
        try {
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
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'oauth_clients')) {
                throw $exception;
            }
        }
    }
}
