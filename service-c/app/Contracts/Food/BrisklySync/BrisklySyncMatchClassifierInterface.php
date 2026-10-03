<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\BrisklySync\SyncResultsDto;

/**
 * Серверная классификация после match (1D / 2B).
 */
interface BrisklySyncMatchClassifierInterface
{
    /**
     * @param  list<SourceMenuLineDto>  $sourceLines
     * @param  list<BrisklySnapshotItemDto>  $brisklySnapshot
     * @param  list<MatchLineResultDto>  $matchLines
     */
    public function classify(
        array $sourceLines,
        array $brisklySnapshot,
        array $matchLines,
        int $cap,
    ): SyncResultsDto;
}
