<?php

declare(strict_types=1);

namespace App\Repositories\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklySyncSessionRepositoryInterface;
use App\DTO\Food\BrisklySync\BrisklySyncSessionRecord;
use App\Enums\Food\BrisklySync\BrisklySyncSessionStatus;
use App\Models\Food\BrisklySyncSession;
use Illuminate\Support\Str;

/**
 * Eloquent-репозиторий сессий Briskly sync.
 */
final class EloquentBrisklySyncSessionRepository implements BrisklySyncSessionRepositoryInterface
{
    public function __construct(
        private readonly BrisklySyncSessionMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): BrisklySyncSessionRecord
    {
        $status = $attributes['status'] ?? BrisklySyncSessionStatus::Setup;
        if ($status instanceof BrisklySyncSessionStatus) {
            $statusValue = $status->value;
        } else {
            $statusValue = (string) $status;
        }

        $model = BrisklySyncSession::query()->create([
            'id' => (string) Str::uuid(),
            'restaurant_id' => (int) $attributes['restaurant_id'],
            'created_by_max_user_id' => $attributes['created_by_max_user_id'] ?? null,
            'vps_category_id' => $attributes['vps_category_id'] ?? null,
            'search_text' => $attributes['search_text'] ?? null,
            'clarification' => $attributes['clarification'] ?? null,
            'status' => $statusValue,
        ]);

        return $this->mapper->toRecord($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $sessionId): ?BrisklySyncSessionRecord
    {
        $model = BrisklySyncSession::query()->find($sessionId);

        return $model instanceof BrisklySyncSession ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $sessionId, array $attributes): BrisklySyncSessionRecord
    {
        $model = BrisklySyncSession::query()->findOrFail($sessionId);

        $payload = [];
        foreach ([
            'restaurant_id',
            'created_by_max_user_id',
            'vps_category_id',
            'search_text',
            'clarification',
            'briskly_snapshot',
            'source_lines_snapshot',
            'proposals',
            'approvals',
            'apply_report',
            'allowed_briskly_category_ids',
            'source_price_hash',
        ] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('status', $attributes)) {
            $status = $attributes['status'];
            $payload['status'] = $status instanceof BrisklySyncSessionStatus
                ? $status->value
                : (string) $status;
        }

        $model->fill($payload);
        $model->save();

        return $this->mapper->toRecord($model->fresh() ?? $model);
    }

    /**
     * {@inheritDoc}
     */
    public function markMatchingAsFailed(string $sessionId): bool
    {
        return BrisklySyncSession::query()
            ->where('id', $sessionId)
            ->where('status', BrisklySyncSessionStatus::Matching->value)
            ->update(['status' => BrisklySyncSessionStatus::Failed->value]) > 0;
    }
}
