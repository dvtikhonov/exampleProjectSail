<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoSidecarHealthProbe;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit: GET sidecar /health → {ok:true}.
 */
final class HttpSchedulePhotoSidecarHealthProbeTest extends TestCase
{
    public function test_health_ok_when_body_has_ok_true(): void
    {
        $http = $this->recordingHttpClient([
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $result = $this->probe($http)->checkHealth();

        $this->assertTrue($result->ok);
        $this->assertSame('Sidecar LLM доступен.', $result->message);
        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/health', $http->lastUrl);
        $this->assertSame('http://127.0.0.1:8791', $http->lastBaseUrl);
        $this->assertSame(5, $http->lastTimeout);
    }

    public function test_health_fails_when_orchestrator_url_empty(): void
    {
        $http = $this->recordingHttpClient([]);
        $probe = new HttpSchedulePhotoSidecarHealthProbe($http, '', 5);

        $result = $probe->checkHealth();

        $this->assertFalse($result->ok);
        $this->assertSame('URL sidecar не настроен.', $result->message);
        $this->assertSame(0, $http->callCount);
    }

    public function test_health_fails_when_ok_false(): void
    {
        $http = $this->recordingHttpClient([
            '/health' => [
                'status' => 200,
                'body' => ['ok' => false],
            ],
        ]);

        $result = $this->probe($http)->checkHealth();

        $this->assertFalse($result->ok);
        $this->assertSame('Sidecar LLM: ответ health без ok:true.', $result->message);
    }

    public function test_health_fails_on_http_error(): void
    {
        $http = $this->recordingHttpClient([
            '/health' => [
                'status' => 503,
                'body' => ['message' => 'down'],
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->checkHealth();

        $this->assertFalse($result->ok);
        $this->assertSame('Sidecar LLM: HTTP 503.', $result->message);
    }

    public function test_health_fails_on_network_error(): void
    {
        $http = new class implements HttpClientInterface
        {
            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                throw new RuntimeException('timed out');
            }
        };

        $result = $this->probe($http)->checkHealth();

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Sidecar LLM недоступен:', $result->message);
        $this->assertStringContainsString('timed out', $result->message);
    }

    /**
     * @param  object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastBaseUrl: ?string,
     *     lastTimeout: int,
     *     callCount: int
     * }&HttpClientInterface  $http
     */
    private function probe(HttpClientInterface $http): HttpSchedulePhotoSidecarHealthProbe
    {
        return new HttpSchedulePhotoSidecarHealthProbe($http, 'http://127.0.0.1:8791/', 5);
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastBaseUrl: ?string,
     *     lastTimeout: int,
     *     callCount: int
     * }&HttpClientInterface
     */
    private function recordingHttpClient(array $routes): HttpClientInterface
    {
        return new class($routes) implements HttpClientInterface
        {
            public string $lastMethod = '';

            public string $lastUrl = '';

            public ?string $lastBaseUrl = null;

            public int $lastTimeout = 0;

            public int $callCount = 0;

            /**
             * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
             */
            public function __construct(private readonly array $routes) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->callCount++;
                $this->lastMethod = $method;
                $this->lastUrl = $url;
                $this->lastBaseUrl = $baseUrl;
                $this->lastTimeout = $timeoutSeconds;

                $route = $this->routes[$url] ?? null;
                if ($route === null) {
                    return new HttpResponseDto(status: 404, body: '{"message":"not stubbed"}', successful: false);
                }

                $status = $route['status'];
                $successful = $route['successful'] ?? ($status >= 200 && $status < 300);
                $body = $route['body'];
                $encoded = is_string($body)
                    ? $body
                    : json_encode($body, JSON_THROW_ON_ERROR);

                return new HttpResponseDto(status: $status, body: $encoded, successful: $successful);
            }
        };
    }
}
