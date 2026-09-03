<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\DTO\Shared\PaginatedResultDto;
use App\Models\Max\MaxUser;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация репозитория пользователей MAX.
 */
class EloquentMaxUserRepository implements MaxUserRepositoryInterface
{
    public function __construct(
        private readonly MaxUserMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listMaxUserIdsWithDeliveryAddress(): array
    {
        return MaxUser::query()
            ->whereNotNull('delivery_address')
            ->whereRaw("TRIM(delivery_address) <> ''")
            ->orderBy('max_user_id')
            ->pluck('max_user_id')
            ->map(static fn (mixed $maxUserId): int => (int) $maxUserId)
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord
    {
        $model = MaxUser::query()->find($maxUserId);

        return $model !== null ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $initData->maxUserId]);

        $maxUser->fill([
            'first_name' => $initData->firstName,
            'last_name' => $initData->lastName,
            'username' => $initData->username,
            'language_code' => $initData->languageCode,
            'photo_url' => $initData->photoUrl,
        ]);

        if ($maxUser->customer_category_id === null && $defaultCustomerCategoryId !== null) {
            $maxUser->customer_category_id = $defaultCustomerCategoryId;
        }

        $maxUser->save();

        return $this->mapper->toRecord($maxUser);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertLoadTestUser(
        int $maxUserId,
        string $firstName,
        string $username,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $maxUserId]);

        if (! $maxUser->exists) {
            $maxUser->fill([
                'first_name' => $firstName,
                'username' => $username,
                'language_code' => 'ru',
            ]);
        }

        if ($maxUser->customer_category_id === null && $defaultCustomerCategoryId !== null) {
            $maxUser->customer_category_id = $defaultCustomerCategoryId;
        }

        $maxUser->save();

        return $this->mapper->toRecord($maxUser);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForManualOrders(?string $query, int $perPage): PaginatedResultDto
    {
        $builder = MaxUser::query()->orderBy('max_user_id');

        $normalizedQuery = $query !== null ? trim($query) : '';

        if ($normalizedQuery !== '') {
            $like = '%'.$normalizedQuery.'%';

            $builder->where(function (Builder $searchQuery) use ($normalizedQuery, $like): void {
                $searchQuery
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('delivery_address', 'like', $like);

                if (ctype_digit($normalizedQuery)) {
                    $searchQuery->orWhere('max_user_id', (int) $normalizedQuery);
                }
            });
        }

        $paginator = $builder->paginate($perPage);

        /** @var list<MaxUserRecord> $items */
        $items = $paginator->getCollection()
            ->map(fn (MaxUser $model): MaxUserRecord => $this->mapper->toRecord($model))
            ->values()
            ->all();

        return new PaginatedResultDto(
            items: $items,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameFieldsSubstring(string $query): array
    {
        $normalizedQuery = trim($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $like = '%'.$normalizedQuery.'%';

        return MaxUser::query()
            ->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like);
            })
            ->orderBy('max_user_id')
            ->get()
            ->map(fn (MaxUser $model): MaxUserRecord => $this->mapper->toRecord($model))
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void
    {
        MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->update(['delivery_address' => $deliveryAddress]);
    }

    /**
     * {@inheritDoc}
     */
    public function clearExpiredAiAccess(DateTimeInterface $now): int
    {
        return MaxUser::query()
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '<=', $now)
            ->update(['ai_access_until' => null]);
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUserRecord
    {
        $model = MaxUser::query()
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '>', $now)
            ->orderBy('max_user_id')
            ->first();

        return $model !== null ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function clearAiAccessForUserIfActive(int $maxUserId, DateTimeInterface $now): int
    {
        return MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->whereNotNull('ai_access_until')
            ->where('ai_access_until', '>', $now)
            ->update(['ai_access_until' => null]);
    }

    /**
     * {@inheritDoc}
     */
    public function setAiAccessUntilIfNoneActive(int $maxUserId, DateTimeInterface $until, DateTimeInterface $now): int
    {
        $table = (new MaxUser)->getTable();

        // Операция атомарна на уровне БД: обновляем строку только если в БД
        // НЕТ ни одной активной записи доступа (NOT EXISTS).
        // MySQL 1093: подзапрос FROM той же таблицы в UPDATE — через derived table.
        return MaxUser::query()
            ->where('max_user_id', $maxUserId)
            ->whereRaw(
                "NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM {$table} WHERE ai_access_until > ?) AS active_ai)",
                [$now],
            )
            ->update(['ai_access_until' => $until]);
    }
}
