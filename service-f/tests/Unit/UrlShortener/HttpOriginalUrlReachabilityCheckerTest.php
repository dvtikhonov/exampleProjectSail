<?php

declare(strict_types=1);

namespace Tests\Unit\UrlShortener;

use App\Services\UrlShortener\HttpOriginalUrlReachabilityChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Unit-тесты HTTP-проверки исходного URL перед сохранением короткой ссылки. */
class HttpOriginalUrlReachabilityCheckerTest extends TestCase
{
    public function test_returns_reachable_when_url_responds_with_200_on_head(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('', 200),
        ]);

        $result = app(HttpOriginalUrlReachabilityChecker::class)->check('https://example.com/page');

        $this->assertTrue($result->isOk());
        $this->assertSame(200, $result->httpStatusCode);
    }

    public function test_returns_not_reachable_when_url_responds_with_non_200_status(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('', 404),
        ]);

        $result = app(HttpOriginalUrlReachabilityChecker::class)->check('https://example.com/missing');

        $this->assertFalse($result->isOk());
        $this->assertSame(404, $result->httpStatusCode);
    }

    public function test_falls_back_to_get_when_head_is_not_supported(): void
    {
        Http::fake([
            'https://example.com/*' => Http::sequence()
                ->push('', 405)
                ->push('', 200),
        ]);

        $result = app(HttpOriginalUrlReachabilityChecker::class)->check('https://example.com/page');

        $this->assertTrue($result->isOk());
        $this->assertSame(200, $result->httpStatusCode);
    }

    public function test_returns_not_reachable_when_connection_fails(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        $result = app(HttpOriginalUrlReachabilityChecker::class)->check('https://example.com/unreachable');

        $this->assertFalse($result->isOk());
        $this->assertNull($result->httpStatusCode);
    }
}
