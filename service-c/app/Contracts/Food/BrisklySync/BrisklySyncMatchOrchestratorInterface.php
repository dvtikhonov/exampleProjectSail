<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Порт к Node orchestrator (Cursor match).
 */
interface BrisklySyncMatchOrchestratorInterface
{
    /**
     * Выполняет match через sidecar; возвращает match_lines без доверия к price.
     *
     * @param  list<SourceMenuLineDto>  $sourceLines
     * @param  list<BrisklySnapshotItemDto>  $brisklySnapshot
     * @return list<MatchLineResultDto>
     *
     * @throws FoodDomainException при недоступности оркестратора (503)
     */
    public function match(
        ComboCatalogPromptDto $prompt,
        array $sourceLines,
        array $brisklySnapshot,
        ?BrisklySyncLlmCallContextDto $logContext = null,
    ): array;
}
