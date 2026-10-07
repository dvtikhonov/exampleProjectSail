<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Exceptions\Food\FoodDomainException;
use App\Infrastructure\Briskly\HttpBrisklySyncTokenCaptureGateway;
use PHPUnit\Framework\TestCase;

/**
 * Контракт POST /capture-token и маппинг ошибок sidecar.
 */
final class HttpBrisklySyncTokenCaptureGatewayTest extends TestCase
{
    public function test_capture_token_sends_secret_header_and_returns_token(): void
    {
        $http = new class implements HttpClientInterface
        {
            public string $lastMethod = '';

            public string $lastUrl = '';

            /** @var array<string, string> */
            public array $lastHeaders = [];

            public ?string $lastBaseUrl = null;

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->lastMethod = $method;
                $this->lastUrl = $url;
                $this->lastHeaders = $headers;
                $this->lastBaseUrl = $baseUrl;

                return new HttpResponseDto(
                    status: 200,
                    body: json_encode(['token' => 'jwt-from-cdp-123456', 'source' => 'cdp'], JSON_THROW_ON_ERROR),
                    successful: true,
                );
            }
        };

        $gateway = new HttpBrisklySyncTokenCaptureGateway(
            $http,
            'http://sidecar.example:8791',
            'shared-secret',
            30,
        );

        $token = $gateway->captureToken();

        $this->assertSame('jwt-from-cdp-123456', $token);
        $this->assertSame('POST', $http->lastMethod);
        $this->assertSame('/capture-token', $http->lastUrl);
        $this->assertSame('http://sidecar.example:8791', $http->lastBaseUrl);
        $this->assertSame('shared-secret', $http->lastHeaders['X-Briskly-Capture-Secret'] ?? null);
    }

    public function test_empty_secret_returns_503_capture_disabled(): void
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
                self::fail('HTTP не должен вызываться без secret');
            }
        };

        $gateway = new HttpBrisklySyncTokenCaptureGateway($http, 'http://sidecar.example:8791', '', 30);

        try {
            $gateway->captureToken();
            $this->fail('Ожидалось FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('захват отключён', $exception->getMessage());
            $this->assertStringNotContainsString('shared-secret', $exception->getMessage());
        }
    }

    public function test_sidecar_error_codes_map_to_status_and_message(): void
    {
        $http = new class implements HttpClientInterface
        {
            public string $errorCode = 'timeout';

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                return new HttpResponseDto(
                    status: 422,
                    body: json_encode(['error' => $this->errorCode], JSON_THROW_ON_ERROR),
                    successful: false,
                );
            }
        };

        $gateway = new HttpBrisklySyncTokenCaptureGateway(
            $http,
            'http://sidecar.example:8791',
            'shared-secret',
            30,
        );

        try {
            $gateway->captureToken();
            $this->fail('Ожидалось FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame(
                'Не удалось получить токен Briskly: истекло время ожидания.',
                $exception->getMessage(),
            );
        }
    }

    public function test_http_timeout_maps_to_cdp_unavailable(): void
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
                throw new \RuntimeException(
                    'cURL error 28: Operation timed out after 30113 milliseconds with 0 bytes received',
                );
            }
        };

        $gateway = new HttpBrisklySyncTokenCaptureGateway(
            $http,
            'http://sidecar.example:8791',
            'shared-secret',
            30,
        );

        try {
            $gateway->captureToken();
            $this->fail('Ожидалось FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertSame(
                'Не удалось получить токен Briskly: Chrome CDP недоступен.',
                $exception->getMessage(),
            );
        }
    }
}
