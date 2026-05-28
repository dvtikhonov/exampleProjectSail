<?php

namespace Tests\Unit\Shared\SalesOutletsDomain\AbstractFilter;

use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PHPUnit\Framework\TestCase;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Composite\CompositeFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereBetweenFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereHasFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereInFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereLikePrefixFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters\WhereNullFilter;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Groups\OrGroup;
use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Groups\WhereGroup;

final class QueryFiltersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_where_filter_applies_where_condition(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('where')
            ->once()
            ->with('status', '=', 'active')
            ->andReturn($query);

        $result = (new WhereFilter('status', '=', 'active'))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_filter_skips_empty_values_and_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereFilter('status', '=', 'active', false), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereFilter('status', '=', null), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereFilter('status', '=', ''), ['where']);
    }

    public function test_where_in_filter_applies_where_in_condition(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('whereIn')
            ->once()
            ->with('id', [1, 2, 3])
            ->andReturn($query);

        $result = (new WhereInFilter('id', [1, 2, 3]))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_in_filter_skips_empty_values_and_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereInFilter('id', [1], false), ['whereIn']);
        $this->assertFilterDoesNotTouchBuilder(new WhereInFilter('id', null), ['whereIn']);
        $this->assertFilterDoesNotTouchBuilder(new WhereInFilter('id', []), ['whereIn']);
    }

    public function test_where_between_filter_applies_between_condition(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2026-01-01', '2026-01-31'])
            ->andReturn($query);

        $result = (new WhereBetweenFilter('created_at', '2026-01-01', '2026-01-31'))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_between_filter_skips_incomplete_range_and_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereBetweenFilter('created_at', '2026-01-01', '2026-01-31', false), ['whereBetween']);
        $this->assertFilterDoesNotTouchBuilder(new WhereBetweenFilter('created_at', null, '2026-01-31'), ['whereBetween']);
        $this->assertFilterDoesNotTouchBuilder(new WhereBetweenFilter('created_at', '2026-01-01', null), ['whereBetween']);
    }

    public function test_where_like_prefix_filter_applies_prefix_like_condition(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('where')
            ->once()
            ->with('name', 'like', 'Shop%')
            ->andReturn($query);

        $result = (new WhereLikePrefixFilter('name', 'Shop'))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_like_prefix_filter_skips_empty_wildcard_and_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereLikePrefixFilter('name', 'Shop', false), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereLikePrefixFilter('name', null), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereLikePrefixFilter('name', ''), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereLikePrefixFilter('name', '%'), ['where']);
        $this->assertFilterDoesNotTouchBuilder(new WhereLikePrefixFilter('name', '%%'), ['where']);
    }

    public function test_where_null_filter_applies_null_condition(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('whereNull')
            ->once()
            ->with('deleted_at')
            ->andReturn($query);

        $result = (new WhereNullFilter('deleted_at'))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_null_filter_applies_not_null_condition_when_negated(): void
    {
        $query = $this->builderMock();

        $query
            ->shouldReceive('whereNotNull')
            ->once()
            ->with('deleted_at')
            ->andReturn($query);

        $result = (new WhereNullFilter('deleted_at', true, true))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_null_filter_skips_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereNullFilter('deleted_at', false), ['whereNull', 'whereNotNull']);
    }

    public function test_composite_filter_applies_filters_in_order_and_returns_last_builder(): void
    {
        $firstQuery = $this->builderMock();
        $secondQuery = $this->builderMock();
        $finalQuery = $this->builderMock();
        $firstFilter = Mockery::mock(Filter::class);
        $secondFilter = Mockery::mock(Filter::class);

        $firstFilter
            ->shouldReceive('apply')
            ->once()
            ->ordered()
            ->with($firstQuery)
            ->andReturn($secondQuery);

        $secondFilter
            ->shouldReceive('apply')
            ->once()
            ->ordered()
            ->with($secondQuery)
            ->andReturn($finalQuery);

        $result = (new CompositeFilter([$firstFilter, $secondFilter]))->apply($firstQuery);

        $this->assertSame($finalQuery, $result);
    }

    public function test_where_group_applies_nested_filters_inside_where_closure(): void
    {
        $query = $this->builderMock();
        $subQuery = $this->builderMock();
        $filter = Mockery::mock(Filter::class);

        $filter
            ->shouldReceive('apply')
            ->once()
            ->with($subQuery)
            ->andReturn($subQuery);

        $query
            ->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function (callable $callback) use ($subQuery): bool {
                $callback($subQuery);

                return true;
            }))
            ->andReturn($query);

        $result = (new WhereGroup([$filter]))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_group_skips_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new WhereGroup([], false), ['where']);
    }

    public function test_or_group_applies_alternatives_inside_grouped_or_closure(): void
    {
        $query = $this->builderMock();
        $groupQuery = $this->builderMock();
        $firstAltQuery = $this->builderMock();
        $secondAltQuery = $this->builderMock();
        $firstAlternative = Mockery::mock(Filter::class);
        $secondAlternative = Mockery::mock(Filter::class);

        $firstAlternative
            ->shouldReceive('apply')
            ->once()
            ->with($firstAltQuery)
            ->andReturn($firstAltQuery);

        $secondAlternative
            ->shouldReceive('apply')
            ->once()
            ->with($secondAltQuery)
            ->andReturn($secondAltQuery);

        $groupQuery
            ->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function (callable $callback) use ($firstAltQuery): bool {
                $callback($firstAltQuery);

                return true;
            }))
            ->andReturn($groupQuery);

        $groupQuery
            ->shouldReceive('orWhere')
            ->once()
            ->with(Mockery::on(function (callable $callback) use ($secondAltQuery): bool {
                $callback($secondAltQuery);

                return true;
            }))
            ->andReturn($groupQuery);

        $query
            ->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function (callable $callback) use ($groupQuery): bool {
                $callback($groupQuery);

                return true;
            }))
            ->andReturn($query);

        $result = (new OrGroup([$firstAlternative, $secondAlternative]))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_or_group_skips_empty_alternatives_and_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(new OrGroup([]), ['where', 'orWhere']);
        $this->assertFilterDoesNotTouchBuilder(new OrGroup([Mockery::mock(Filter::class)], false), ['where', 'orWhere']);
    }

    public function test_where_has_filter_applies_nested_filter_to_relation_query(): void
    {
        $query = $this->builderMock();
        $relationQuery = $this->builderMock();
        $filter = Mockery::mock(Filter::class);

        $filter
            ->shouldReceive('apply')
            ->once()
            ->with($relationQuery)
            ->andReturn($relationQuery);

        $query
            ->shouldReceive('whereHas')
            ->once()
            ->with('organization', Mockery::on(function (callable $callback) use ($relationQuery): bool {
                $callback($relationQuery);

                return true;
            }))
            ->andReturn($query);

        $result = (new WhereHasFilter('organization', $filter))->apply($query);

        $this->assertSame($query, $result);
    }

    public function test_where_has_filter_skips_disabled_state(): void
    {
        $this->assertFilterDoesNotTouchBuilder(
            new WhereHasFilter('organization', Mockery::mock(Filter::class), false),
            ['whereHas']
        );
    }

    /**
     * @param  list<string>  $methods
     */
    private function assertFilterDoesNotTouchBuilder(Filter $filter, array $methods): void
    {
        $query = $this->builderMock();

        foreach ($methods as $method) {
            $query->shouldReceive($method)->never();
        }

        $this->assertSame($query, $filter->apply($query));
    }

    private function builderMock(): Builder
    {
        return Mockery::mock(Builder::class);
    }
}
