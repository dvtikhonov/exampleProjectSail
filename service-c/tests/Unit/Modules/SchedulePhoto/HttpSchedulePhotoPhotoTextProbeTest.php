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
        $this->assertSame(200, $result->status);
        $this->assertSame('Локальный PhotoText доступен.', $result->message);
        $this->assertStringNotContainsString('VPS', $result->message);
        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/restaurants', $http->lastUrl);
        $this->assertSame('http://localhost:8083', $http->lastBaseUrl);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertSame(10, $http->lastTimeout);
        $this->assertFalse($http->lastFollowRedirects);
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
        $this->assertSame(0, $result->status);
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
        $this->assertSame(401, $result->status);
        $this->assertSame('VPS PhotoText: неверный токен (401).', $result->message);
    }

    public function test_probe_403_known_ai_denial_passes_fixed_phrase(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => '  Доступ AI к базе не разрешён.  '],
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame(403, $result->status);
        $this->assertSame('Доступ AI к базе не разрешён.', $result->message);
    }

    public function test_probe_403_arbitrary_body_message_is_not_reflected(): void
    {
        $foreign = 'WAF blocked /internal stack trace APP_DEBUG';
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => $foreign],
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame(403, $result->status);
        $this->assertSame('VPS PhotoText: HTTP 403.', $result->message);
        $this->assertStringNotContainsString($foreign, $result->message);
    }

    public function test_probe_403_local_origin_arbitrary_body_is_not_called_vps(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => 'debug dump of local config'],
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->probe('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertSame('Локальный PhotoText: HTTP 403.', $result->message);
        $this->assertStringNotContainsString('VPS', $result->message);
        $this->assertStringNotContainsString('debug dump', $result->message);
    }

    public function test_probe_403_without_body_message_keeps_http_status(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => 'not-json',
                'successful' => false,
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame(403, $result->status);
        $this->assertSame('VPS PhotoText: HTTP 403.', $result->message);
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
        $this->assertSame(200, $result->status);
        $this->assertSame('VPS PhotoText: пустой список ресторанов.', $result->message);
    }

    public function test_probe_redirect_keeps_redirect_status_and_does_not_follow(): void
    {
        foreach ([301, 302] as $status) {
            $http = $this->recordingHttpClient([
                '/api/food/phototext/restaurants' => [
                    'status' => $status,
                    'body' => '',
                    'successful' => false,
                ],
            ]);

            $result = $this->probe($http)->probe('http://vps.example');

            $this->assertFalse($result->ok);
            $this->assertSame($status, $result->status);
            $this->assertSame('VPS PhotoText: HTTP '.$status.'.', $result->message);
            $this->assertFalse($http->lastFollowRedirects);
        }
    }

    public function test_probe_non_json_200_reports_no_phototext_status(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => '<html>login</html>',
            ],
        ]);

        $result = $this->probe($http)->probe('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertSame(0, $result->status);
        $this->assertSame('VPS PhotoText: пустой или неверный JSON.', $result->message);
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
                bool $followRedirects = true,
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
     *     lastFollowRedirects: bool,
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
     *     lastFollowRedirects: bool,
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

            public bool $lastFollowRedirects = true;

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
                bool $followRedirects = true,
            ): HttpResponseDto {
                $this->callCount++;
                $this->lastMethod = $method;
                $this->lastUrl = $url;
                $this->lastHeaders = $headers;
                $this->lastBaseUrl = $baseUrl;
                $this->lastTimeout = $timeoutSeconds;
                $this->lastFollowRedirects = $followRedirects;

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
