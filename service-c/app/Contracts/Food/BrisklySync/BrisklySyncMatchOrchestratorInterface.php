<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Порт к Node orchestrator (Cursor match): handshake start + abort.
 */
interface BrisklySyncMatchOrchestratorInterface
{
    /**
     * Handshake: Agent.create + send на sidecar. Успех — 202 running, иначе 503.
     * Классификация и Matched — через completeQueuedMatch после колбэка.
     *
     * @param  list<SourceMenuLineDto>  $sourceLines
     * @param  list<BrisklySnapshotItemDto>  $brisklySnapshot
     *
     * @throws FoodDomainException при недоступности оркестратора (503)
     */
    public function start(
        ComboCatalogPromptDto $prompt,
        array $sourceLines,
        array $brisklySnapshot,
        string $sessionId,
        string $matchGeneration,
        ?BrisklySyncLlmCallContextDto $logContext = null,
    ): void;

    /**
     * Best-effort отмена колбэка успеха для generation (SDK cancel нет).
     */
    public function abort(string $sessionId, string $matchGeneration): void;
}
