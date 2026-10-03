<?php

declare(strict_types=1);

namespace App\Repositories\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklySyncSessionRecord;
use App\Enums\Food\BrisklySync\BrisklySyncSessionStatus;
use App\Models\Food\BrisklySyncSession;

/**
 * Маппер Eloquent ↔ BrisklySyncSessionRecord.
 */
final class BrisklySyncSessionMapper
{
    public function toRecord(BrisklySyncSession $model): BrisklySyncSessionRecord
    {
        $allowed = $model->allowed_briskly_category_ids;
        $allowedIds = null;
        if (is_array($allowed)) {
            $allowedIds = array_values(array_map(static fn ($id): int => (int) $id, $allowed));
        }

        /** @var list<array<string, mixed>>|null $snapshot */
        $snapshot = is_array($model->briskly_snapshot) ? $model->briskly_snapshot : null;
        /** @var list<array<string, mixed>>|null $sourceLines */
        $sourceLines = is_array($model->source_lines_snapshot) ? $model->source_lines_snapshot : null;
        /** @var array<string, mixed>|null $proposals */
        $proposals = is_array($model->proposals) ? $model->proposals : null;
        /** @var array<string, mixed>|null $approvals */
        $approvals = is_array($model->approvals) ? $model->approvals : null;
        /** @var array<string, mixed>|null $applyReport */
        $applyReport = is_array($model->apply_report) ? $model->apply_report : null;

        return new BrisklySyncSessionRecord(
            id: (string) $model->id,
            restaurantId: (int) $model->restaurant_id,
            createdByMaxUserId: $model->created_by_max_user_id !== null
                ? (int) $model->created_by_max_user_id
                : null,
            vpsCategoryId: $model->vps_category_id !== null ? (int) $model->vps_category_id : null,
            searchText: $model->search_text !== null ? (string) $model->search_text : null,
            clarification: $model->clarification !== null ? (string) $model->clarification : null,
            status: BrisklySyncSessionStatus::from((string) $model->status),
            brisklySnapshot: $snapshot,
            sourceLinesSnapshot: $sourceLines,
            proposals: $proposals,
            approvals: $approvals,
            applyReport: $applyReport,
            allowedBrisklyCategoryIds: $allowedIds,
            sourcePriceHash: $model->source_price_hash !== null
                ? (string) $model->source_price_hash
                : null,
        );
    }
}
