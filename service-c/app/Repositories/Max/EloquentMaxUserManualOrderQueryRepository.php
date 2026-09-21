<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserManualOrderQueryRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Models\Max\MaxUser;
use App\Support\Database\LikeEscape;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация manual-order query порта пользователей MAX.
 */
class EloquentMaxUserManualOrderQueryRepository implements MaxUserManualOrderQueryRepositoryInterface
{
    public function __construct(
        private readonly MaxUserMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function paginateForManualOrders(?string $query, int $perPage): PaginatedResultDto
    {
        $builder = MaxUser::query()->orderBy('max_user_id');

        $normalizedQuery = $query !== null ? trim($query) : '';

        if ($normalizedQuery !== '') {
            $like = LikeEscape::contains($normalizedQuery);
            $escape = LikeEscape::ESCAPE_CHAR;

            $builder->where(function (Builder $searchQuery) use ($normalizedQuery, $like, $escape): void {
                $searchQuery
                    ->whereRaw('first_name LIKE ? ESCAPE ?', [$like, $escape])
                    ->orWhereRaw('last_name LIKE ? ESCAPE ?', [$like, $escape])
                    ->orWhereRaw('username LIKE ? ESCAPE ?', [$like, $escape]);

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

        $like = LikeEscape::contains($normalizedQuery);
        $escape = LikeEscape::ESCAPE_CHAR;

        return MaxUser::query()
            ->where(function (Builder $searchQuery) use ($like, $escape): void {
                $searchQuery
                    ->whereRaw('first_name LIKE ? ESCAPE ?', [$like, $escape])
                    ->orWhereRaw('last_name LIKE ? ESCAPE ?', [$like, $escape])
                    ->orWhereRaw('username LIKE ? ESCAPE ?', [$like, $escape]);
            })
            ->orderBy('max_user_id')
            ->limit(3)
            ->get()
            ->map(fn (MaxUser $model): MaxUserRecord => $this->mapper->toRecord($model))
            ->values()
            ->all();
    }
}
