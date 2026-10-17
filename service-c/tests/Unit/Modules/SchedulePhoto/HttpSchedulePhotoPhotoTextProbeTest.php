<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoPhotoTextProbe;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit: probe PhotoText restaurants + X-PhotoText-Token.
 * Локальный origin в тексте готовности не называется VPS.
 */
final class HttpSchedulePhotoPhotoTextProbeTest extends TestCase
{
    public function test_probe_ok_when_restaurants_non_empty(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
        ]);

        $result = $this->probe($http)->probe('http://localhost:8083/');

        $this->assertTrue($result->ok);
        $this->assertSame('Локальный PhotoText доступен.', $result->message);
        $this->assertStringNotContainsString('VPS', $result->message);
        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/restaurants', $http->lastUrl);
        $this->assertSame('http://localhost:8083', $http->lastBaseUrl);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertSame(10, $http->lastTimeout);
    }

    public function test_probe_local_docker_origin_is_not_called_vps(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
        ]);

        $result = $this->probe($http)->probe('http://host.docker.internal:8083/');

        $this->assertTrue($result->ok);
        $this->assertSame('Локальный PhotoText доступен.', $result->message);
        $this->assertStringNotContainsString('VPS', $result->message);
        $this->assertSame('http://host.docker.internal:8083', $http->lastBaseUrl);
    }

    public function test_probe_fails_on_empty_base_url(): void
    {
        $http = $this->recordingHttpClient([]);
        $result = $this->probe($http)->probe('   ');

        $this->assertFalse($result->ok);
        $this->assertSame('BASE_URL пуст.', $result->message);
        $this->assertSame(0, $http->callCount);
    }

    public function test_probe_fails_when_agent_token_missing(): void
    {
        $http = $this->recordingHttpClient([]);
        $probe = new HttpSchedulePhotoPhotoTextProbe($http, '', 10);

        $result = $probe->probe('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertSame('PhotoText agent token не настроен на сервере.', $result->message);
        $this->assertSame(0, $http->callCount);
    }

    public function test_probe_fails_on_401(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 401,
                'body' => ['message' => 'unauthorized'],
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame('VPS PhotoText: неверный токен (401).', $result->message);
    }

    public function test_probe_fails_on_empty_restaurants(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => ['restaurants' => []],
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame('VPS PhotoText: пустой список ресторанов.', $result->message);
    }

    public function test_probe_fails_on_network_error(): void
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
                throw new RuntimeException('Connection refused');
            }
        };

        $result = $this->probe($http)->probe('http://dead.example');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('VPS PhotoText недоступен:', $result->message);
        $this->assertStringContainsString('Connection refused', $result->message);
    }

    /**
     * @param  object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
     *     lastBaseUrl: ?string,
     *     lastTimeout: int,
     *     callCount: int
     * }&HttpClientInterface  $http
     */
    private function probe(HttpClientInterface $http): HttpSchedulePhotoPhotoTextProbe
    {
        return new HttpSchedulePhotoPhotoTextProbe($http, 'agent-token-test', 10);
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
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

            /** @var array<string, string> */
            public array $lastHeaders = [];

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
                $this->lastHeaders = $headers;
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
