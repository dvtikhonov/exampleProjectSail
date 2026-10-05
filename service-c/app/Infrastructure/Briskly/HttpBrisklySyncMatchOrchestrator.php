<?php

declare(strict_types=1);

namespace App\Infrastructure\Briskly;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\LlmCallLoggerInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\DTO\Shared\LlmCallExchangeDto;
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
        private readonly LlmCallLoggerInterface $llmCallLogger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(
        ComboCatalogPromptDto $prompt,
        array $sourceLines,
        array $brisklySnapshot,
        ?BrisklySyncLlmCallContextDto $logContext = null,
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

        $actor = $logContext?->actorLabel() ?? 'max_user_id=неизвестно';
        $meta = $this->metaFromContext($logContext);

        try {
            $response = $this->http->request(
                'POST',
                '/match',
                ['Accept' => 'application/json'],
                $payload,
                rtrim($this->baseUrl, '/'),
                $this->timeoutSeconds,
            );
        } catch (\Throwable $exception) {
            $this->llmCallLogger->logExchange(new LlmCallExchangeDto(
                provider: 'briskly-sync',
                operation: 'match',
                actor: $actor,
                requestText: $this->formatRequestText($payload),
                errorText: $exception->getMessage(),
                meta: $meta,
            ));
            throw $exception;
        }

        if (! $response->successful) {
            $detail = $this->orchestratorErrorDetail($response->body);
            $this->llmCallLogger->logExchange(new LlmCallExchangeDto(
                provider: 'briskly-sync',
                operation: 'match',
                actor: $actor,
                requestText: $this->formatRequestText($payload),
                responseText: $this->bodyToText($response->body),
                errorText: 'HTTP '.$response->status.' — Orchestrator Briskly sync недоступен.'.$detail,
                meta: $meta,
            ));
            throw new FoodDomainException(
                'Orchestrator Briskly sync недоступен.'.$detail,
                503,
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            $this->llmCallLogger->logExchange(new LlmCallExchangeDto(
                provider: 'briskly-sync',
                operation: 'match',
                actor: $actor,
                requestText: $this->formatRequestText($payload),
                responseText: $this->bodyToText($response->body),
                errorText: 'Некорректный ответ orchestrator.',
                meta: $meta,
            ));
            throw new FoodDomainException('Некорректный ответ orchestrator.', 503);
        }

        $matchLinesRaw = $decoded['match_lines'] ?? [];
        if (! is_array($matchLinesRaw)) {
            $this->llmCallLogger->logExchange(new LlmCallExchangeDto(
                provider: 'briskly-sync',
                operation: 'match',
                actor: $actor,
                requestText: $this->formatRequestText($payload),
                responseText: $this->encodeAsText($decoded),
                errorText: 'Orchestrator не вернул match_lines.',
                meta: $meta,
            ));
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

        $this->llmCallLogger->logExchange(new LlmCallExchangeDto(
            provider: 'briskly-sync',
            operation: 'match',
            actor: $actor,
            requestText: $this->formatRequestText($payload),
            responseText: $this->formatResponseText($decoded, $result),
            meta: $meta,
        ));

        return $result;
    }

    /**
     * @return array<string, string|int|null>
     */
    private function metaFromContext(?BrisklySyncLlmCallContextDto $logContext): array
    {
        if ($logContext === null) {
            return [];
        }

        return [
            'session_id' => $logContext->sessionId,
            'restaurant_id' => $logContext->restaurantId,
            'created_by_max_user_id' => $logContext->createdByMaxUserId,
        ];
    }

    /**
     * @param  array{
     *     prompt: array<string, mixed>,
     *     source_lines: list<array<string, mixed>>,
     *     briskly_snapshot: list<array<string, mixed>>
     * }  $payload
     */
    private function formatRequestText(array $payload): string
    {
        $prompt = $payload['prompt'];
        $system = is_string($prompt['system'] ?? null) ? $prompt['system'] : $this->encodeAsText($prompt['system'] ?? null);
        $user = is_string($prompt['user'] ?? null) ? $prompt['user'] : $this->encodeAsText($prompt['user'] ?? null);

        return implode("\n", [
            '=== prompt.system ===',
            $system,
            '=== prompt.user ===',
            $user,
            '=== source_lines (VPS) ===',
            $this->encodeAsText($payload['source_lines']),
            '=== briskly_snapshot ===',
            $this->encodeAsText($payload['briskly_snapshot']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<MatchLineResultDto>  $result
     */
    private function formatResponseText(array $decoded, array $result): string
    {
        $sections = [
            '=== match_lines ===',
            $this->encodeAsText(array_map(
                static fn (MatchLineResultDto $line): array => $line->toArray(),
                $result,
            )),
        ];

        if (isset($decoded['raw_text']) && is_string($decoded['raw_text']) && $decoded['raw_text'] !== '') {
            $sections[] = '=== raw_text (LLM) ===';
            $sections[] = $decoded['raw_text'];
        }

        if (isset($decoded['sync_results'])) {
            $sections[] = '=== sync_results ===';
            $sections[] = $this->encodeAsText($decoded['sync_results']);
        }

        return implode("\n", $sections);
    }

    private function bodyToText(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '(пустое тело ответа)';
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $this->encodeAsText($decoded);
        }

        return $trimmed;
    }

    private function encodeAsText(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );

        return $encoded === false ? '' : $encoded;
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
