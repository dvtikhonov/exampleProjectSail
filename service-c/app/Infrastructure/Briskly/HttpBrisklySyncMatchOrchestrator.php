<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * HTTP-клиент к Node sidecar briskly-sync (POST /match).
 */
final class HttpBrisklySyncMatchOrchestrator implements BrisklySyncMatchOrchestratorInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(
        ComboCatalogPromptDto $prompt,
        array $sourceLines,
        array $brisklySnapshot,
    ): array {
        $payload = [
            'prompt' => $prompt->toArray(),
            'source_lines' => array_map(
                static fn (SourceMenuLineDto $line): array => $line->toArray(),
                $sourceLines,
            ),
            'briskly_snapshot' => array_map(
                static fn (BrisklySnapshotItemDto $item): array => $item->toArray(),
                $brisklySnapshot,
            ),
        ];

        $response = $this->http->request(
            'POST',
            '/match',
            ['Accept' => 'application/json'],
            $payload,
            rtrim($this->baseUrl, '/'),
            $this->timeoutSeconds,
        );

        if (! $response->successful) {
            $detail = $this->orchestratorErrorDetail($response->body);
            throw new FoodDomainException(
                'Orchestrator Briskly sync недоступен.'.$detail,
                503,
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new FoodDomainException('Некорректный ответ orchestrator.', 503);
        }

        $matchLinesRaw = $decoded['match_lines'] ?? [];
        if (! is_array($matchLinesRaw)) {
            throw new FoodDomainException('Orchestrator не вернул match_lines.', 503);
        }

        $result = [];
        foreach ($matchLinesRaw as $row) {
            if (! is_array($row)) {
                continue;
            }
            // Любой price от LLM отбрасывается на уровне fromArray (не читаем).
            $result[] = MatchLineResultDto::fromArray($row);
        }

        return $result;
    }

    /**
     * Короткий текст ошибки из тела sidecar (без утечки секретов).
     */
    private function orchestratorErrorDetail(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $message = trim($decoded['message']);
            if ($message !== '') {
                return ' '.$this->truncateDetail($message);
            }
        }

        return ' '.$this->truncateDetail($trimmed);
    }

    private function truncateDetail(string $detail): string
    {
        if (mb_strlen($detail) <= 280) {
            return $detail;
        }

        return mb_substr($detail, 0, 277).'...';
    }
}
