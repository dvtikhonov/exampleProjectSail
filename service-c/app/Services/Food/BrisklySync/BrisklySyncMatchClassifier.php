<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklySyncMatchClassifierInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\PriceDiffItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\BrisklySync\SyncResultsCountsDto;
use App\DTO\Food\BrisklySync\SyncResultsDto;
use App\DTO\Food\BrisklySync\SyncResultsSectionDto;
use App\DTO\Food\BrisklySync\VpsOnlyCreateItemDto;

/**
 * Классификация match → PriceDiff / VpsOnlyCreate / counters (1D / 2B).
 */
final class BrisklySyncMatchClassifier implements BrisklySyncMatchClassifierInterface
{
    /**
     * {@inheritDoc}
     */
    public function classify(
        array $sourceLines,
        array $brisklySnapshot,
        array $matchLines,
        int $cap,
    ): SyncResultsDto {
        $matchByKey = [];
        foreach ($matchLines as $matchLine) {
            if (! $matchLine instanceof MatchLineResultDto) {
                continue;
            }
            if (! isset($matchByKey[$matchLine->lineKey])) {
                $matchByKey[$matchLine->lineKey] = $matchLine;
            }
        }

        $brisklyById = [];
        foreach ($brisklySnapshot as $item) {
            if ($item instanceof BrisklySnapshotItemDto) {
                $brisklyById[$item->id] = $item;
            }
        }

        $priceDiffs = [];
        $creates = [];
        $matchedBrisklyIds = [];
        $ambiguous = 0;
        $equalPrice = 0;

        foreach ($sourceLines as $sourceLine) {
            if (! $sourceLine instanceof SourceMenuLineDto) {
                continue;
            }

            $match = $matchByKey[$sourceLine->lineKey] ?? null;
            if ($match === null || $match->candidates === []) {
                $creates[] = new VpsOnlyCreateItemDto(
                    lineKey: $sourceLine->lineKey,
                    displayName: $sourceLine->displayName,
                    sourcePrice: BrisklySyncPrice::normalize($sourceLine->price),
                    brisklyCreateName: $sourceLine->brisklyCreateName !== ''
                        ? $sourceLine->brisklyCreateName
                        : $sourceLine->displayName,
                );
                continue;
            }

            if (count($match->candidates) > 1) {
                $ambiguous++;
                continue;
            }

            $candidate = $match->candidates[0];
            $briskly = $brisklyById[$candidate->id] ?? null;
            if ($briskly === null) {
                $creates[] = new VpsOnlyCreateItemDto(
                    lineKey: $sourceLine->lineKey,
                    displayName: $sourceLine->displayName,
                    sourcePrice: BrisklySyncPrice::normalize($sourceLine->price),
                    brisklyCreateName: $sourceLine->brisklyCreateName !== ''
                        ? $sourceLine->brisklyCreateName
                        : $sourceLine->displayName,
                );
                continue;
            }

            $matchedBrisklyIds[$briskly->id] = true;

            if (BrisklySyncPrice::equal($sourceLine->price, $briskly->price)) {
                $equalPrice++;
                continue;
            }

            $priceDiffs[] = new PriceDiffItemDto(
                lineKey: $sourceLine->lineKey,
                displayName: $sourceLine->displayName,
                brisklyItemId: $briskly->id,
                brisklyDisplayName: $briskly->name !== '' ? $briskly->name : $candidate->name,
                sourcePrice: BrisklySyncPrice::normalize($sourceLine->price),
                brisklyPrice: BrisklySyncPrice::normalize($briskly->price),
                compareName: $match->compareName,
            );
        }

        $skippedBrisklyOnly = 0;
        foreach ($brisklySnapshot as $item) {
            if ($item instanceof BrisklySnapshotItemDto && ! isset($matchedBrisklyIds[$item->id])) {
                $skippedBrisklyOnly++;
            }
        }

        return new SyncResultsDto(
            priceUpdates: SyncResultsSectionDto::fromAll($priceDiffs, $cap),
            creates: SyncResultsSectionDto::fromAll($creates, $cap),
            counts: new SyncResultsCountsDto(
                skippedBrisklyOnly: $skippedBrisklyOnly,
                ambiguous: $ambiguous,
                equalPrice: $equalPrice,
            ),
        );
    }
}
