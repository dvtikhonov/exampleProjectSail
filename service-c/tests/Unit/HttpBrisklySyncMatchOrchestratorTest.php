<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Infrastructure\Briskly\HttpBrisklySyncMatchOrchestrator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Контракт POST /match и запись исходящего LLM-промпта в max_log.
 */
final class HttpBrisklySyncMatchOrchestratorTest extends TestCase
{
    public function test_match_logs_full_prompt_before_http_call(): void
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
                    ], JSON_THROW_ON_ERROR),
                    successful: true,
                );
            }
        };

        $logger = new class extends AbstractLogger
        {
            /** @var list<array{level: string|mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
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

        $result = $orchestrator->match($prompt, $sourceLines, $snapshot);

        $this->assertCount(1, $result);
        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Briskly sync LLM prompt', $logger->records[0]['message']);
        $this->assertSame($prompt->system, $logger->records[0]['context']['system']);
        $this->assertSame($prompt->user, $logger->records[0]['context']['user']);
        $this->assertSame(1, $logger->records[0]['context']['source_lines_count']);
        $this->assertSame(1, $logger->records[0]['context']['briskly_snapshot_count']);
        $this->assertSame($prompt->toArray(), $http->lastJsonBody['prompt'] ?? null);
    }
}
