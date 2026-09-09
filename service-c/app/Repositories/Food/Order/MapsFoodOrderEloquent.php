<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Models\Food\FoodOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Общий Eloquent→Record маппинг для репозиториев заказов еды.
 */
trait MapsFoodOrderEloquent
{
    public function __construct(
        private readonly FoodOrderMapper $mapper,
    ) {}

    /**
     * Преобразует Eloquent-модель заказа в доменную проекцию.
     */
    protected function mapToRecord(FoodOrder $model): FoodOrderRecord
    {
        return $this->mapper->toRecord($model);
    }

    /**
     * Пагинирует Eloquent-запрос и возвращает страницу доменных Record.
     *
     * @param  Builder<FoodOrder>  $query
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    protected function paginateRecords(Builder $query, int $perPage): PaginatedResultDto
    {
        $paginator = $query->paginate($perPage);

        /** @var list<FoodOrderRecord> $records */
        $records = $paginator->getCollection()
            ->map(fn (FoodOrder $model): FoodOrderRecord => $this->mapToRecord($model))
            ->values()
            ->all();

        return new PaginatedResultDto(
            items: $records,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}
