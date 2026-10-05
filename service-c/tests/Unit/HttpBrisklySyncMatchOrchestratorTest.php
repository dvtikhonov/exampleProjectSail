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
use App\Infrastructure\Briskly\HttpBrisklySyncMatchOrchestrator;
use PHPUnit\Framework\TestCase;

/**
 * Контракт POST /match к orchestrator sidecar + логирование LLM.
 */
final class HttpBrisklySyncMatchOrchestratorTest extends TestCase
{
    public function test_match_sends_prompt_and_parses_match_lines(): void
    {
        $http = new class implements HttpClientInterface
        {
            public ?array $lastJsonBody = null;

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->lastJsonBody = $jsonBody;

                return new HttpResponseDto(
                    status: 200,
                    body: json_encode([
                        'match_lines' => [
                            [
                                'line_key' => 'src-1',
                                'display_name' => 'Салат',
                                'compare_name' => 'салат',
                                'candidates' => [],
                            ],
                        ],
                        'raw_text' => '{"match_lines":[]}',
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
            120,
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

        $result = $orchestrator->match(
            $prompt,
            $sourceLines,
            $snapshot,
            new BrisklySyncLlmCallContextDto(
                sessionId: 'sess-1',
                restaurantId: 7,
                createdByMaxUserId: 42,
            ),
        );

        $this->assertCount(1, $result);
        $this->assertSame('src-1', $result[0]->lineKey);
        $this->assertSame($prompt->toArray(), $http->lastJsonBody['prompt'] ?? null);

        $this->assertNotNull($logger->last);
        $this->assertSame('briskly-sync', $logger->last->provider);
        $this->assertSame('match', $logger->last->operation);
        $this->assertSame('max_user_id=42', $logger->last->actor);
        $this->assertStringContainsString('=== prompt.system ===', $logger->last->requestText);
        $this->assertStringContainsString('=== source_lines (VPS) ===', $logger->last->requestText);
        $this->assertStringContainsString('=== briskly_snapshot ===', $logger->last->requestText);
        $this->assertStringContainsString('Салат', $logger->last->requestText);
        $this->assertStringContainsString('=== match_lines ===', (string) $logger->last->responseText);
        $this->assertStringContainsString('=== raw_text (LLM) ===', (string) $logger->last->responseText);
        $this->assertSame('sess-1', $logger->last->meta['session_id'] ?? null);
        $this->assertSame(7, $logger->last->meta['restaurant_id'] ?? null);
    }
}
