<?php

namespace Shared\SalesOutletsDomain\Query\SalesOutletFilterQuery;

use Illuminate\Database\Eloquent\Builder;

class FilterQuerySalesOutletComposite
{
    protected SalesOutletFilterFactory $filterFactory;

    public function __construct(
        protected ?array      $filterData = [],
        ?SalesOutletFilterFactory $filterFactory = null,
    )
    {
        $this->filterFactory = $filterFactory ?? app(SalesOutletFilterFactory::class);
    }

    /**
     * Собрать в фильтр запроса при наличии данных для фильтрации
     *
     * @param $data
     * @return void
     */
    public function run($data): void
    {
        $query = $data->query;
        $filterData = $data->filterData;
        if (!($query instanceof Builder)) {
            return;
        }

        $this->filterFactory
            ->fromArrayData($filterData)
            ->apply($query);
    }
}
