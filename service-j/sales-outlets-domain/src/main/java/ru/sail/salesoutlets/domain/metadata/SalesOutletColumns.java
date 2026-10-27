package ru.sail.salesoutlets.domain.metadata;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Метаданные колонок таблицы торговых точек (паритет с shared SalesOutletColumns).
 */
public final class SalesOutletColumns {

    public static final String FILTER_LIKE_PREFIX = "like_prefix";
    public static final String FILTER_STATUS_LABEL = "status_label";

    private static final List<SalesOutletColumn> COLUMNS = List.of(
            column("id", "ID объекта продаж", true, true, FILTER_LIKE_PREFIX, null),
            column("shop", "Магазин", true, true, FILTER_LIKE_PREFIX, null),
            column("manager", "Менеджер", true, true, FILTER_LIKE_PREFIX, null),
            column("curator", "Куратор ТТ", true, true, FILTER_LIKE_PREFIX, null),
            column("name", "Название ТТ", true, true, FILTER_LIKE_PREFIX, null),
            column("inn", "ИНН головной", true, true, FILTER_LIKE_PREFIX, null),
            column("head_organization", "Головная организация", true, true, FILTER_LIKE_PREFIX, null),
            column("head_organization_type", "Вид", true, true, FILTER_LIKE_PREFIX, null),
            column("organization_name", "Название организации", true, true, FILTER_LIKE_PREFIX, null),
            column("status_label", "Статус", true, false, FILTER_STATUS_LABEL, "status"),
            column("approved", "Одобрено", true, true, FILTER_LIKE_PREFIX, null),
            column("user_id", "Последний пользователь", true, true, null, null)
    );

    private SalesOutletColumns() {
    }

    public static List<SalesOutletColumn> all() {
        return COLUMNS;
    }

    public static List<String> keys() {
        return COLUMNS.stream().map(SalesOutletColumn::key).toList();
    }

    /**
     * DB-колонки для глобального поиска ({@code search} query param).
     */
    public static List<String> searchableDbColumns() {
        return COLUMNS.stream()
                .filter(SalesOutletColumn::searchable)
                .map(SalesOutletColumn::dbColumn)
                .toList();
    }

    /**
     * API sort key → DB column для ORDER BY.
     */
    public static Map<String, String> sortColumnMap() {
        Map<String, String> map = new LinkedHashMap<>();
        for (SalesOutletColumn column : COLUMNS) {
            if (column.sortable()) {
                map.put(column.key(), column.dbColumn());
            }
        }
        return Map.copyOf(map);
    }

    /**
     * Колонки с column_filters: API key → filterType.
     */
    public static Map<String, String> columnFilterTypeMap() {
        Map<String, String> map = new LinkedHashMap<>();
        for (SalesOutletColumn column : COLUMNS) {
            column.filterTypeOptional().ifPresent(type -> map.put(column.key(), type));
        }
        return Map.copyOf(map);
    }

    /**
     * DB-колонка для like_prefix column filter по API key.
     */
    public static Map<String, String> likePrefixFilterColumnMap() {
        Map<String, String> map = new LinkedHashMap<>();
        for (SalesOutletColumn column : COLUMNS) {
            if (FILTER_LIKE_PREFIX.equals(column.filterType())) {
                map.put(column.key(), column.dbColumn());
            }
        }
        return Map.copyOf(map);
    }

    private static SalesOutletColumn column(
            String key,
            String label,
            boolean sortable,
            boolean searchable,
            String filterType,
            String sortColumn
    ) {
        return new SalesOutletColumn(key, label, sortable, searchable, filterType, sortColumn);
    }
}
