<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

/**
 * Применяет фильтры, поиск и сортировку к Doctrine QueryBuilder.
 * Логика фильтрации совместима с shared/sales-outlets-domain.
 */
final class DoctrineSalesOutletQueryApplicator
{
    /**
     * @param array<int, string> $allowedColumnKeys
     */
    public function apply(QueryBuilder $queryBuilder, SalesOutletFilterDto $filters, array $allowedColumnKeys): void
    {
        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $alias));

        $this->applyStatus($queryBuilder, $alias, $filters->status);
        $this->applyColumnFilters($queryBuilder, $alias, $filters->columnFilters);
        $this->applySearch($queryBuilder, $alias, $filters->search);
        $this->applySort($queryBuilder, $alias, $filters->sort, $filters->direction);
    }

    private function applyStatus(QueryBuilder $queryBuilder, string $alias, string $status): void
    {
        if (null === SalesOutletStatus::tryFrom($status)) {
            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.status = :statusFilter', $alias))
            ->setParameter('statusFilter', $status);
    }

    /**
     * @param array<string, string> $columnFilters
     */
    private function applyColumnFilters(QueryBuilder $queryBuilder, string $alias, array $columnFilters): void
    {
        foreach (SalesOutletColumns::likePrefixFilterColumnMap() as $columnKey => $dbColumn) {
            $prefix = $columnFilters[$columnKey] ?? '';

            if ('' === $prefix || '%' === $prefix || '%%' === $prefix) {
                continue;
            }

            $property = $this->dbColumnToProperty($dbColumn);
            $parameter = sprintf('columnFilter_%s', $columnKey);

            $queryBuilder
                ->andWhere(sprintf('%s.%s LIKE :%s', $alias, $property, $parameter))
                ->setParameter($parameter, $prefix.'%');
        }

        $filterTypes = SalesOutletColumns::columnFilterTypeMap();

        if (($filterTypes['status_label'] ?? null) === SalesOutletColumns::FILTER_STATUS_LABEL) {
            $statuses = $this->statusesByLabel($columnFilters['status_label'] ?? '');

            if ([] !== $statuses) {
                $queryBuilder
                    ->andWhere(sprintf('%s.status IN (:statusLabelFilter)', $alias))
                    ->setParameter('statusLabelFilter', $statuses);
            }
        }
    }

    private function applySearch(QueryBuilder $queryBuilder, string $alias, string $search): void
    {
        if ('' === $search) {
            return;
        }

        $searchColumns = SalesOutletColumns::searchableDbColumns();
        $conditions = [];

        foreach ($searchColumns as $index => $dbColumn) {
            $property = $this->dbColumnToProperty($dbColumn);
            $parameter = sprintf('searchFilter_%d', $index);
            $conditions[] = sprintf('%s.%s LIKE :%s', $alias, $property, $parameter);
            $queryBuilder->setParameter($parameter, '%'.$search.'%');
        }

        if ([] === $conditions) {
            return;
        }

        $queryBuilder->andWhere('('.implode(' OR ', $conditions).')');
    }

    private function applySort(QueryBuilder $queryBuilder, string $alias, string $sort, string $direction): void
    {
        $sortColumns = SalesOutletColumns::sortColumnMap();
        $dbColumn = $sortColumns[$sort] ?? 'id';
        $property = $this->dbColumnToProperty($dbColumn);
        $orderDirection = 'desc' === strtolower($direction) ? 'DESC' : 'ASC';

        $queryBuilder->orderBy(sprintf('%s.%s', $alias, $property), $orderDirection);
    }

    /**
     * @return array<int, string>
     */
    /** Фильтрует статусы по подстроке в человекочитаемой метке. */
    private function statusesByLabel(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        $needle = mb_strtolower($value);

        return array_values(array_map(
            static fn (SalesOutletStatus $status): string => $status->value,
            array_filter(
                SalesOutletStatus::cases(),
                static fn (SalesOutletStatus $status): bool => str_contains(
                    mb_strtolower($status->label()),
                    $needle,
                ),
            ),
        ));
    }

    /** Преобразует имя колонки БД (snake_case) в свойство Entity (camelCase). */
    private function dbColumnToProperty(string $dbColumn): string
    {
        return lcfirst(str_replace('_', '', ucwords($dbColumn, '_')));
    }
}
