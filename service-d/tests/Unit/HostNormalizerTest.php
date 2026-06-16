<?php

namespace Tests\Unit;

use App\Support\HostNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostNormalizerTest extends TestCase
{
    #[DataProvider('hostProvider')]
    public function test_normalizes_host_values(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, HostNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function hostProvider(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', null],
            'literal null' => ['null', null],
            'hostname' => ['yandexmaps.example.test', 'yandexmaps.example.test'],
            'hostname with port' => ['localhost:8080', 'localhost:8080'],
            'https url' => ['https://yandexmaps.94-228-117-27.sslip.io', 'yandexmaps.94-228-117-27.sslip.io'],
            'http url with port' => ['http://localhost:8084/', 'localhost:8084'],
            'https url trailing slash' => ['https://yandexmaps.example.test/', 'yandexmaps.example.test'],
        ];
    }
}
