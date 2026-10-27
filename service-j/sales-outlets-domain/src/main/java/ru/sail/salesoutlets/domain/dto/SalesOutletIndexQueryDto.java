package ru.sail.salesoutlets.domain.dto;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Параметры запроса списка торговых точек (после валидации HTTP-слоя).
 *
 * @param search         глобальный поиск
 * @param status         фильтр статуса
 * @param columnFilters  фильтры по колонкам
 * @param sort           ключ сортировки
 * @param direction      {@code asc}/{@code desc}
 * @param page           номер страницы (≥ 1)
 * @param perPage        размер страницы (5..50)
 * @param columns        видимые колонки (API keys)
 */
public record SalesOutletIndexQueryDto(
        String search,
        String status,
        Map<String, String> columnFilters,
        String sort,
        String direction,
        int page,
        int perPage,
        List<String> columns
) {
    public static final int MIN_PER_PAGE = 5;
    public static final int MAX_PER_PAGE = 50;
    public static final int DEFAULT_PER_PAGE = 10;

    public SalesOutletIndexQueryDto {
        search = search == null ? "" : search.trim();
        status = status == null ? "" : status.trim();
        columnFilters = columnFilters == null ? Map.of() : Map.copyOf(columnFilters);
        sort = sort == null || sort.isBlank() ? "id" : sort;
        direction = "desc".equalsIgnoreCase(direction) ? "desc" : "asc";
        page = Math.max(page, 1);
        perPage = Math.min(Math.max(perPage, MIN_PER_PAGE), MAX_PER_PAGE);
        columns = columns == null || columns.isEmpty() ? List.of() : List.copyOf(columns);
    }

    /**
     * Собирает DTO из сырых query-параметров с нормализацией пагинации и колонок.
     *
     * @param search            параметр search
     * @param status            параметр status
     * @param requestedFilters  параметр column_filters
     * @param sort              параметр sort
     * @param direction         параметр direction
     * @param page              параметр page
     * @param perPage           параметр per_page
     * @param requestedColumns  параметр columns (null = все allowed)
     * @param allowedColumns    разрешённые ключи колонок
     */
    public static SalesOutletIndexQueryDto normalize(
            String search,
            String status,
            Map<String, String> requestedFilters,
            String sort,
            String direction,
            Integer page,
            Integer perPage,
            List<String> requestedColumns,
            List<String> allowedColumns
    ) {
        List<String> allowed = allowedColumns == null ? List.of() : List.copyOf(allowedColumns);
        List<String> columns = resolveColumns(requestedColumns, allowed);

        return new SalesOutletIndexQueryDto(
                search,
                status,
                resolveColumnFilters(requestedFilters, allowed),
                sort,
                direction,
                page == null ? 1 : page,
                perPage == null ? DEFAULT_PER_PAGE : perPage,
                columns
        );
    }

    private static List<String> resolveColumns(List<String> requestedColumns, List<String> allowedColumns) {
        if (requestedColumns == null) {
            return allowedColumns;
        }
        List<String> columns = new ArrayList<>();
        for (String key : requestedColumns) {
            if (key != null && allowedColumns.contains(key) && !columns.contains(key)) {
                columns.add(key);
            }
        }
        return columns.isEmpty() ? allowedColumns : List.copyOf(columns);
    }

    private static Map<String, String> resolveColumnFilters(
            Map<String, String> requestedFilters,
            List<String> allowedColumns
    ) {
        if (requestedFilters == null || requestedFilters.isEmpty()) {
            return Map.of();
        }
        Map<String, String> filters = new LinkedHashMap<>();
        for (String column : allowedColumns) {
            String value = requestedFilters.get(column);
            if (value == null) {
                continue;
            }
            String trimmed = value.trim();
            if (!trimmed.isEmpty()) {
                filters.put(column, trimmed);
            }
        }
        return Map.copyOf(filters);
    }
}
