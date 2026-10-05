<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Briskly\BrisklySyncSourceOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Разбор origin из MAX_MINI_APP_URL и флаг remote source (host ≠ APP_URL).
 */
final class BrisklySyncSourceOriginTest extends TestCase
{
    #[DataProvider('fromMiniAppUrlProvider')]
    public function test_from_mini_app_url_strips_path_keeps_origin(string $miniAppUrl, string $expected): void
    {
        $this->assertSame($expected, BrisklySyncSourceOrigin::fromMiniAppUrl($miniAppUrl));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function fromMiniAppUrlProvider(): array
    {
        return [
            'sslip with max-app' => [
                'https://94-228-117-27.sslip.io/max-app',
                'https://94-228-117-27.sslip.io',
            ],
            'localhost with path' => [
                'http://localhost/max-app',
                'http://localhost',
            ],
            'with port' => [
                'https://example.test:8443/max-app/foo',
                'https://example.test:8443',
            ],
            'trailing spaces' => [
                '  https://prod.example/max-app  ',
                'https://prod.example',
            ],
            'empty' => ['', ''],
            'whitespace only' => ['   ', ''],
            'relative path' => ['/max-app', ''],
            'no host' => ['https:///max-app', ''],
        ];
    }

    #[DataProvider('isRemoteProvider')]
    public function test_is_remote_compares_hosts(
        string $sourceBaseUrl,
        string $appUrl,
        bool $expected,
    ): void {
        $this->assertSame($expected, BrisklySyncSourceOrigin::isRemote($sourceBaseUrl, $appUrl));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function isRemoteProvider(): array
    {
        return [
            'same host local' => [
                'http://localhost',
                'http://localhost',
                false,
            ],
            'same host ignore case' => [
                'https://App.Example',
                'https://app.example/max-app',
                false,
            ],
            'different hosts remote' => [
                'https://94-228-117-27.sslip.io',
                'http://localhost',
                true,
            ],
            'empty source not remote' => [
                '',
                'http://localhost',
                false,
            ],
            'source set app empty → remote' => [
                'https://prod.example',
                '',
                true,
            ],
        ];
    }
}
