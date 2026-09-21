<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderManualAdminReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;
use App\Support\Database\LikeEscape;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация чтения ручных заказов для административного API.
 */
class EloquentFoodOrderManualAdminReadRepository implements FoodOrderManualAdminReadRepositoryInterface
{
    use MapsFoodOrderEloquent;

    /**
     * {@inheritDoc}
     */
    public function paginateManualOrders(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): PaginatedResultDto {
        return $this->paginateRecords(
            $this->manualOrdersQuery(
                $query,
                $dateFrom,
                $dateTo,
                $customerMaxUserId,
                $status,
            )
                ->with(['restaurant', 'maxUser'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sumManualOrdersTotal(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): string {
        $sum = $this->manualOrdersQuery(
            $query,
            $dateFrom,
            $dateTo,
            $customerMaxUserId,
            $status,
        )->sum('total');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * {@inheritDoc}
     */
    public function findManualOrderById(int $id): ?FoodOrderRecord
    {
        $model = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->withExists('messages')
            ->where('is_manual', true)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->mapToRecord($model) : null;
    }

    /**
     * Базовый запрос ручных заказов с фильтрами списка.
     *
     * @return Builder<FoodOrder>
     */
    private function manualOrdersQuery(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): Builder {
        $builder = FoodOrder::query()->where('is_manual', true);

        if ($customerMaxUserId !== null) {
            $builder->where('max_user_id', $customerMaxUserId);
        }

        if ($status !== null) {
            $builder->where('status', $status);
        }

        if ($dateFrom !== null) {
            $builder->where('created_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo !== null) {
            $builder->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        $normalizedQuery = $query !== null ? trim($query) : '';

        if ($normalizedQuery !== '') {
            $like = LikeEscape::contains($normalizedQuery);
            $escape = LikeEscape::ESCAPE_CHAR;

            $builder->whereHas('maxUser', function (Builder $userQuery) use ($normalizedQuery, $like, $escape): void {
                $userQuery->where(function (Builder $searchQuery) use ($normalizedQuery, $like, $escape): void {
                    $searchQuery
                        ->whereRaw('first_name LIKE ? ESCAPE ?', [$like, $escape])
                        ->orWhereRaw('last_name LIKE ? ESCAPE ?', [$like, $escape])
                        ->orWhereRaw('username LIKE ? ESCAPE ?', [$like, $escape])
                        ->orWhereRaw(
                            "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ? ESCAPE ?",
                            [$like, $escape],
                        );

                    if (ctype_digit($normalizedQuery)) {
                        $searchQuery->orWhere('max_user_id', (int) $normalizedQuery);
                    }
                });
            });
        }

        return $builder;
    }
}
