<?php

namespace Shared\SalesOutletsDomain\Metadata;

final class SalesOutletColumns
{
    public const FILTER_LIKE_PREFIX = 'like_prefix';

    public const FILTER_STATUS_LABEL = 'status_label';

    /**
     * @var array<int, array{
     *     key: string,
     *     label: string,
     *     sortable: bool,
     *     searchable?: bool,
     *     filterType?: string|null,
     *     sortColumn?: string
     * }>
     */
    private const COLUMNS = [
        ['key' => 'id', 'label' => 'ID объекта продаж', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'shop', 'label' => 'Магазин', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'manager', 'label' => 'Менеджер', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'curator', 'label' => 'Куратор ТТ', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'name', 'label' => 'Название ТТ', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'inn', 'label' => 'ИНН головной', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'head_organization', 'label' => 'Головная организация', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'head_organization_type', 'label' => 'Вид', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'organization_name', 'label' => 'Название организации', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        [
            'key' => 'status_label',
            'label' => 'Статус',
            'sortable' => true,
            'searchable' => false,
            'filterType' => self::FILTER_STATUS_LABEL,
            'sortColumn' => 'status',
        ],
        ['key' => 'approved', 'label' => 'Одобрено', 'sortable' => true, 'searchable' => true, 'filterType' => self::FILTER_LIKE_PREFIX],
        ['key' => 'user_id', 'label' => 'Последний пользователь', 'sortable' => true, 'searchable' => true],
    ];

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     sortable: bool,
     *     searchable?: bool,
     *     filterType?: string|null,
     *     sortColumn?: string
     * }>
     */
    public static function all(): array
    {
        return self::COLUMNS;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::COLUMNS, 'key');
    }

    /**
     * DB-колонки для глобального поиска (`search` query param).
     *
     * @return array<int, string>
     */
    public static function searchableDbColumns(): array
    {
        $columns = [];

        foreach (self::COLUMNS as $column) {
            if (($column['searchable'] ?? false) !== true) {
                continue;
            }

            $columns[] = self::dbColumn($column);
        }

        return $columns;
    }

    /**
     * API sort key → DB column для ORDER BY.
     *
     * @return array<string, string>
     */
    public static function sortColumnMap(): array
    {
        $map = [];

        foreach (self::COLUMNS as $column) {
            if (($column['sortable'] ?? false) !== true) {
                continue;
            }

            $map[$column['key']] = self::dbColumn($column);
        }

        return $map;
    }

    /**
     * Колонки с column_filters: API key → filterType.
     *
     * @return array<string, string>
     */
    public static function columnFilterTypeMap(): array
    {
        $map = [];

        foreach (self::COLUMNS as $column) {
            $filterType = $column['filterType'] ?? null;

            if ($filterType === null || $filterType === '') {
                continue;
            }

            $map[$column['key']] = $filterType;
        }

        return $map;
    }

    /**
     * DB-колонка для like_prefix column filter по API key.
     *
     * @return array<string, string>
     */
    public static function likePrefixFilterColumnMap(): array
    {
        $map = [];

        foreach (self::COLUMNS as $column) {
            if (($column['filterType'] ?? null) !== self::FILTER_LIKE_PREFIX) {
                continue;
            }

            $map[$column['key']] = self::dbColumn($column);
        }

        return $map;
    }

    /**
     * @param  array{key: string, sortColumn?: string}  $column
     */
    private static function dbColumn(array $column): string
    {
        return $column['sortColumn'] ?? $column['key'];
    }
}
