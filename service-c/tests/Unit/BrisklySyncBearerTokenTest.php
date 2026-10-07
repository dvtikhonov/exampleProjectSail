<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\BrisklySync\BrisklySyncBearerToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BrisklySyncBearerTokenTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize_strips_bearer_prefix_and_whitespace(string $raw, string $expected): void
    {
        $this->assertSame($expected, BrisklySyncBearerToken::normalize($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'plain jwt' => ['eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
            'with Bearer' => ['Bearer eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
            'with bearer lower' => ['bearer  eyJhbGciOiJIUzI1NiJ9.payload.sig', 'eyJhbGciOiJIUzI1NiJ9.payload.sig'],
            'trimmed' => ["  eyJ.x.y  \n", 'eyJ.x.y'],
            'empty' => ['   ', ''],
        ];
    }

    public function test_is_expired_detects_past_exp(): void
    {
        $payload = rtrim(strtr(base64_encode('{"exp":1000}'), '+/', '-_'), '=');
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.'.$payload.'.sig';
        $this->assertTrue(BrisklySyncBearerToken::isExpired($jwt, 2000));
        $this->assertFalse(BrisklySyncBearerToken::isExpired($jwt, 500));
    }
}
