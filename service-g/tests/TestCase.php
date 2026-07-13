<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Отключает Vite и CSRF для stateful API (Sanctum). */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
