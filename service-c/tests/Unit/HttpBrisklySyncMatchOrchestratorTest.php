<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\LlmCallLoggerInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\DTO\Shared\HttpResponseDto;
use App\DTO\Shared\LlmCallExchangeDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Exceptions\Food\FoodDomainException;
use App\Infrastructure\Briskly\HttpBrisklySyncMatchOrchestrator;
use PHPUnit\Framework\TestCase;

/**
 * Контракт POST /match handshake и POST /match/abort.
 */
final class HttpBrisklySyncMatchOrchestratorTest extends TestCase
{
    public function test_start_sends_generation_and_accepts_202_running(): void
    {
        $http = new class implements HttpClientInterface
        {
            public ?array $lastJsonBody = null;

            public string $lastUrl = '';

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->lastUrl = $url;
                $this->lastJsonBody = $jsonBody;

                return new HttpResponseDto(
                    status: 202,
                    body: json_encode([
                        'accepted' => true,
                        'phase' => 'running',
                    ], JSON_THROW_ON_ERROR),
                    successful: true,
                );
            }
        };

        $logger = new class implements LlmCallLoggerInterface
        {
            public ?LlmCallExchangeDto $last = null;

            public function logExchange(LlmCallExchangeDto $exchange): void
            {
                $this->last = $exchange;
            }
        };

        $orchestrator = new HttpBrisklySyncMatchOrchestrator(
            $http,
            'http://sidecar.example:8791',
            60,
            $logger,
        );

        $prompt = new ComboCatalogPromptDto(
            system: "SYSTEM PROMPT\nline 2",
            user: "USER PROMPT\n{\"restaurant_id\":1}",
        );

        $sourceLines = [
            new SourceMenuLineDto(
                lineKey: 'src-1',
                type: DailyMenuLineType::Single,
                displayName: 'Салат',
                price: '100.00',
                partDishIds: [1],
            ),
        ];
        $snapshot = [
            new BrisklySnapshotItemDto(
                id: 10,
                name: 'Салат морковь',
                price: '120.00',
            ),
        ];

        $orchestrator->start(
            $prompt,
            $sourceLines,
            $snapshot,
            'sess-1',
            'gen-1',
            new BrisklySyncLlmCallContextDto(
                sessionId: 'sess-1',
                restaurantId: 7,
                createdByMaxUserId: 42,
            ),
        );

        $this->assertSame('/match', $http->lastUrl);
        $this->assertSame($prompt->toArray(), $http->lastJsonBody['prompt'] ?? null);
        $this->assertSame('sess-1', $http->lastJsonBody['session_id'] ?? null);
        $this->assertSame('gen-1', $http->lastJsonBody['match_generation'] ?? null);

        $this->assertNotNull($logger->last);
        $this->assertSame('briskly-sync', $logger->last->provider);
        $this->assertSame('match', $logger->last->operation);
        $this->assertSame('max_user_id=42', $logger->last->actor);
        $this->assertStringContainsString('=== prompt.system ===', $logger->last->requestText);
        $this->assertStringContainsString('=== handshake ===', (string) $logger->last->responseText);
        $this->assertSame('sess-1', $logger->last->meta['session_id'] ?? null);
        $this->assertSame('gen-1', $logger->last->meta['match_generation'] ?? null);
        $this->assertSame(7, $logger->last->meta['restaurant_id'] ?? null);
    }

    public function test_start_503_when_sidecar_rejects_handshake(): void
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
                return new HttpResponseDto(
                    status: 503,
                    body: json_encode(['message' => 'no_network'], JSON_THROW_ON_ERROR),
                    successful: false,
                );
            }
        };

        $logger = new class implements LlmCallLoggerInterface
        {
            public function logExchange(LlmCallExchangeDto $exchange): void {}
        };

        $orchestrator = new HttpBrisklySyncMatchOrchestrator(
            $http,
            'http://sidecar.example:8791',
            60,
            $logger,
        );

        try {
            $orchestrator->start(
                new ComboCatalogPromptDto(system: 's', user: 'u'),
                [
                    new SourceMenuLineDto(
                        lineKey: 'src-1',
                        type: DailyMenuLineType::Single,
                        displayName: 'Салат',
                        price: '100.00',
                        partDishIds: [1],
                    ),
                ],
                [new BrisklySnapshotItemDto(10, 'Салат', '120.00')],
                'sess-1',
                'gen-1',
            );
            $this->fail('Ожидался 503 handshake.');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }
    }

    public function test_abort_posts_generation_and_swallows_errors(): void
    {
        $http = new class implements HttpClientInterface
        {
            public ?array $lastJsonBody = null;

            public string $lastUrl = '';

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->lastUrl = $url;
                $this->lastJsonBody = $jsonBody;
                throw new \RuntimeException('network down');
            }
        };

        $logger = new class implements LlmCallLoggerInterface
        {
            public function logExchange(LlmCallExchangeDto $exchange): void {}
        };

        $orchestrator = new HttpBrisklySyncMatchOrchestrator(
            $http,
            'http://sidecar.example:8791',
            60,
            $logger,
        );

        $orchestrator->abort('sess-1', 'gen-1');

        $this->assertSame('/match/abort', $http->lastUrl);
        $this->assertSame('sess-1', $http->lastJsonBody['session_id'] ?? null);
        $this->assertSame('gen-1', $http->lastJsonBody['match_generation'] ?? null);
    }
}
